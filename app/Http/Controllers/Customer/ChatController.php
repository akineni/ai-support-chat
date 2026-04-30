<?php

namespace App\Http\Controllers\Customer;

use App\Enums\MessageSenderType;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SendMessageRequest;
use App\Http\Requests\Customer\StartConversationRequest;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Services\ConversationService;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer Chat
 */
class ChatController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
        protected MessageService      $messageService,
    ) {}

    /**
     * Start a conversation
     *
     * @unauthenticated
     *
     * @bodyParam customer_name string required Example: John Doe
     * @bodyParam customer_email string optional Example: john@example.com
     *
     * @response 201 {"status":"success","message":"Conversation started","data":{"session_token":"550e8400-e29b-41d4-a716-446655440000","conversation":{"uuid":"a1b2c3d4-e5f6-7890-abcd-ef1234567890","customer_name":"John Doe","customer_email":"john@example.com","status":"open","mode":"ai","assigned_agent":null,"taken_over_at":null,"created_at":"2025-01-01T00:00:00.000000Z"}}}
     * @response 422 {"status":"error","message":"The customer name field is required.","errors":{"customer_name":["The customer name field is required."]}}
     */
    public function start(StartConversationRequest $request): JsonResponse
    {
        $result = $this->conversationService->startConversation($request->validated());

        return ApiResponse::success('Conversation started', [
            'session_token' => $result['session_token'],
            'conversation'  => new ConversationResource($result['conversation']),
        ], 201);
    }

    /**
     * Get message history
     *
     * @unauthenticated
     *
     * @urlParam sessionToken string required Example: 550e8400-e29b-41d4-a716-446655440000
     *
     * @response 200 {"status":"success","message":"Messages retrieved","data":[{"sender_type":"customer","body":"Hello, I need help with my order.","is_read":true,"attachments":[],"created_at":"2025-01-01T00:00:00.000000Z"},{"sender_type":"ai","body":"Hi John! I'd be happy to help. Could you provide your order number?","is_read":true,"attachments":[],"created_at":"2025-01-01T00:00:05.000000Z"}],"meta":{"current_page":1,"per_page":50,"total":2}}
     * @response 404 {"status":"error","message":"Conversation not found."}
     */
    public function history(string $sessionToken): JsonResponse
    {
        $messages = $this->conversationService->getConversationHistoryForCustomer($sessionToken);

        return ApiResponse::success(
            'Messages retrieved',
            ApiCollection::for($messages, MessageResource::class)
        );
    }

    /**
     * Send a message
     *
     * AI replies automatically in AI mode. Supports up to 5 attachments (jpg, png, gif, webp, pdf, doc, docx, txt, zip — max 10MB each).
     *
     * @unauthenticated
     *
     * @urlParam sessionToken string required Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam body string optional Required if no attachments. Example: Hi, I need help with my order #12345
     * @bodyParam attachments file[] optional Up to 5 files, max 10MB each.
     *
     * @response 200 {"status":"success","message":"Message sent","data":{"sender_type":"ai","body":"Thanks John! I can see order #12345 in our system.","is_read":false,"attachments":[],"created_at":"2025-01-01T00:00:15.000000Z"}}
     * @response 404 {"status":"error","message":"Conversation not found."}
     * @response 422 {"status":"error","message":"This conversation is closed."}
     */
    public function sendMessage(SendMessageRequest $request, string $sessionToken): JsonResponse
    {
        $reply = $this->messageService->handleCustomerMessage(
            $sessionToken,
            $request->input('body', ''),
            $request->file('attachments', [])
        );

        return ApiResponse::success(
            'Message sent',
            new MessageResource($reply)
        );
    }

    /**
     * Broadcast customer typing status
     *
     * @unauthenticated
     * @urlParam sessionToken string required Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam is_typing bool required Example: true
     */
    public function typing(Request $request, string $sessionToken): JsonResponse
    {
        $this->conversationService->broadcastTyping(
            $sessionToken,
            MessageSenderType::CUSTOMER->value,
            (bool) $request->input('is_typing', false)
        );

        return ApiResponse::success('OK');
    }
}