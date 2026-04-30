<?php

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Enums\MessageSenderType;
use App\Events\ConversationTakenOver;
use App\Events\MessageSent;
use App\Exceptions\ConflictException;
use App\Exceptions\ConversationClosedException;
use App\Helpers\FileUploadHelper;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\AttachmentRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessAiReply;

class MessageService
{
    public function __construct(
        protected MessageRepositoryInterface      $messageRepository,
        protected ConversationRepositoryInterface $conversationRepository,
        protected AttachmentRepositoryInterface   $attachmentRepository,
        protected AiChatService                   $aiChatService,
        protected DocumentExtractorService        $documentExtractor,
    ) {}

    // -------------------------------------------------------
    // Public — Customer
    // -------------------------------------------------------

    public function handleCustomerMessage(
        string $sessionToken,
        string $body,
        array $files = []
    ): Message {
        $conversation = app(ConversationService::class)->getConversationBySessionToken($sessionToken);

        if ($conversation->isClosed()) {
            throw new ConversationClosedException();
        }

        return DB::transaction(function () use ($conversation, $body, $files) {
            $customerMessage = $this->persistCustomerMessage($conversation, $body);
            $attachments     = $this->handleAttachments($customerMessage, $files);

            $this->broadcastMessage($customerMessage);

            if ($conversation->isHumanMode()) {
                return $customerMessage->refresh();
            }

            $this->dispatchAiReply($conversation, $body, $attachments);

            return $customerMessage->refresh();
        });
    }

    // -------------------------------------------------------
    // Public — Agent
    // -------------------------------------------------------

    public function handleAgentReply(
        string $uuid,
        int $agentId,
        string $body
    ): Message {
        $conversation = app(ConversationService::class)->getConversationByUuid($uuid);

        if ($conversation->isAiMode()) {
            throw new ConflictException('Take over the conversation before replying.');
        }

        $message = $this->persistAgentMessage($conversation, $agentId, $body);

        $this->broadcastMessage($message);

        return $message;
    }

    // -------------------------------------------------------
    // Private — Message Persistence
    // -------------------------------------------------------

    private function persistCustomerMessage(Conversation $conversation, string $body): Message
    {
        return $this->messageRepository->create([
            'conversation_id' => $conversation->id,
            'sender_type'     => MessageSenderType::CUSTOMER,
            'body'            => $body ?: null,
        ]);
    }

    private function persistAgentMessage(
        Conversation $conversation,
        int $agentId,
        string $body
    ): Message {
        return $this->messageRepository->create([
            'conversation_id' => $conversation->id,
            'sender_type'     => MessageSenderType::AGENT,
            'agent_id'        => $agentId,
            'body'            => $body,
        ]);
    }

    private function persistAiMessage(Conversation $conversation, string $body): Message
    {
        return $this->messageRepository->create([
            'conversation_id' => $conversation->id,
            'sender_type'     => MessageSenderType::AI,
            'body'            => $body,
        ]);
    }

    // -------------------------------------------------------
    // Private — Broadcasting
    // -------------------------------------------------------

    private function broadcastMessage(Message $message): void
    {
        broadcast(new MessageSent(
            $message->load('attachments')
        ))->toOthers();
    }

    // -------------------------------------------------------
    // Private — Attachments
    // -------------------------------------------------------

    private function handleAttachments(Message $message, array $files): array
    {
        if (empty($files)) {
            return ['imageUrls' => [], 'fileNames' => [], 'extractedContent' => []];
        }

        return $this->processAttachments($message, $files);
    }

    private function processAttachments(Message $message, array $files): array
    {
        $imageUrls        = [];
        $fileNames        = [];
        $extractedContent = [];
        $attachmentData   = [];

        /*
        * ATTACHMENT HANDLING — WHAT THE AI CAN AND CANNOT READ
        * -------------------------------------------------------
        * All file types (images, documents, archives) are uploaded to Cloudinary
        * and stored in the database for the customer and agent to access.
        *
        * However, what the AI can actually process is limited by the provider's API:
        *
        * IMAGES (jpg, jpeg, png, gif, webp)
        *    Passed to the AI via the vision API as image URLs.
        *    The AI can see, describe, and reason about the image content.
        *
        * DOCUMENTS (pdf, doc, docx, txt)
        *    The AI cannot fetch or read files from a URL — it only receives
        *    text and images as structured input. Passing a Cloudinary URL for
        *    a .txt or .docx file is meaningless to the AI; it has no mechanism
        *    to download and read it.
        *
        *    When DOCUMENT_EXTRACTION_ENABLED=true, text is extracted server-side
        *    and passed as plain text in the message context so the AI can read it.
        *    When disabled, the AI is informed of the file name only and escalates
        *    to a human agent — which is the recommended default for sensitive
        *    documents (e.g. pension documents, IDs, certificates).
        *
        * ARCHIVES (zip)
        *    No meaningful content can be extracted or passed to the AI.
        *    The AI is informed that a file was attached via the file name only.
        *
        * In all non-image cases, the file is always stored and accessible to
        * human agents via the conversation dashboard regardless of extraction setting.
        */

        foreach ($files as $file) {
            /** @var UploadedFile $file */
            [$url, $isImage] = $this->uploadFile($file);

            $attachmentData[] = $this->buildAttachmentData($file, $url, $isImage);

            if ($isImage) {
                $imageUrls[] = $url;
            } else {
                $fileNames[] = $file->getClientOriginalName();
                $extracted   = $this->documentExtractor->extract($file);

                if ($extracted) {
                    $extractedContent[] = [
                        'file_name' => $file->getClientOriginalName(),
                        'content'   => $extracted,
                    ];
                }
            }
        }

        $this->attachmentRepository->bulkCreateForMessage($message, $attachmentData);

        return [
            'imageUrls'        => $imageUrls,
            'fileNames'        => $fileNames,
            'extractedContent' => $extractedContent,
        ];
    }

    private function uploadFile(UploadedFile $file): array
    {
        $url     = FileUploadHelper::singleBinaryFileUpload(
            $file,
            'chat-attachments',
            'attach_'
        );
        $isImage = str_starts_with($file->getMimeType() ?? '', 'image/');

        return [$url, $isImage];
    }

    private function buildAttachmentData(UploadedFile $file, string $url, bool $isImage): array
    {
        return [
            'file_url'       => $url,
            'file_name'      => $file->getClientOriginalName(),
            'file_type'      => $file->getMimeType(),
            'file_extension' => $file->getClientOriginalExtension(),
            'file_size'      => $file->getSize(),
            'is_image'       => $isImage,
        ];
    }

    private function dispatchAiReply(
        Conversation $conversation,
        string $body,
        array $attachments
    ): void {
        ProcessAiReply::dispatch(
            $conversation->id,
            $body,
            $attachments['imageUrls'],
            $attachments['fileNames'],
            $attachments['extractedContent']
        );
    }
}
