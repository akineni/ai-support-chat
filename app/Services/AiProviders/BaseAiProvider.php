<?php

namespace App\Services\AiProviders;

use App\Enums\MessageSenderType;
use App\Models\Conversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

abstract class BaseAiProvider implements AiProviderInterface
{
    // -------------------------------------------------------
    // Abstract - Each provider must define these
    // -------------------------------------------------------

    /**
     * Return the provider-specific HTTP headers for authentication.
     */
    abstract protected function headers(): array;

    /**
     * Return image content blocks in the format the provider expects.
     */
    abstract protected function buildImageBlocks(array $imageUrls): array;

    /**
     * Build the full request payload for the provider's API.
     */
    abstract protected function buildPayload(
        Conversation $conversation,
        Collection $history,
        string $newMessage,
        array $imageUrls,
        array $fileNames,
        array $extractedContent
    ): array;

    // -------------------------------------------------------
    // Shared - System Prompt
    // -------------------------------------------------------

    /**
     * Builds the full system prompt, shared by all providers.
     *
     * Structure:
     *   1. Role & identity
     *   2. Strict scope rules
     *   3. Behavioural rules
     *   4. Knowledge base  ← conditionally included only when a source is registered and available
     *   5. Conversation context (customer name)
     *
     * All company-specific values are driven by config('services.company.*').
     * Nothing in this prompt is hardcoded to any particular business or industry.
     */
    public static function systemPrompt(Conversation $conversation): string
    {
        $companyName   = config('services.company.name');
        $companyScope  = config('services.company.scope'); // e.g. "our products and services"
        $knowledgeBase = KnowledgeBase::build();

        // Only inject the knowledge base section when a source is registered and returns content.
        // If no source is plugged in, or the source has nothing to return, this block is omitted
        // entirely and the AI falls back to its behavioural rules alone - no errors, no empty sections.
        $knowledgeBaseSection = $knowledgeBase
            ? <<<SECTION

            === KNOWLEDGE BASE ===
            Use the following as your primary source of truth when answering customer questions.
            Always prefer answers from this knowledge base over any general knowledge you may have.
            If a question is within scope but not covered here, say you don't have that specific
            information and offer to escalate.

            {$knowledgeBase}
            SECTION
            : '';

        $prompt = <<<PROMPT
        You are a friendly and professional customer support agent for {$companyName}.
        Your name is "Support Team". You respond helpfully, concisely, and warmly.

        === SCOPE RESTRICTION ===
        You are exclusively a customer support assistant for {$companyName}.
        You MUST ONLY answer questions that are directly related to {$companyName} and {$companyScope}.

        If a customer asks ANYTHING outside of this scope - including but not limited to general knowledge,
        other companies, entertainment, health advice, coding, politics, or any other unrelated topic -
        you MUST respond with exactly this message and nothing else:
        "I'm sorry, I can only assist with matters related to {$companyName} and {$companyScope}.
        Is there anything I can help you with?"

        Do NOT attempt to answer off-topic questions even partially.
        Do NOT apologise extensively - one short sentence is enough before redirecting.

        === BEHAVIOURAL RULES ===
        - Never reveal you are an AI unless directly and explicitly asked.
        - If asked directly whether you are an AI, answer honestly.
        - If you cannot resolve an issue, say "Let me escalate this to a specialist for you"
          and include the exact string [ESCALATE] at the very end of your response.
        - If the customer uses phrases like "speak to a human", "real agent", or "manager",
          respond with "Of course! Let me connect you with one of our team members right away."
          and include [ESCALATE] at the very end of your response.
        - If the customer seems very upset or frustrated, offer to escalate.
        - Keep responses concise and focused.
        - Do not make up policies, prices, or information you are not certain about.
        - If a customer attaches a non-image file (like a PDF or Word document), acknowledge
          it warmly and let them know a human agent will review it.
        {$knowledgeBaseSection}
        === CONVERSATION CONTEXT ===
        Customer name: {$conversation->customer_name}
        PROMPT;

        // Log::debug('AI system prompt', [
        //     'conversation_id'      => $conversation->id,
        //     'knowledge_base_loaded' => !empty($knowledgeBase),
        //     'prompt'               => $prompt,
        // ]);

        return $prompt;
    }

    // -------------------------------------------------------
    // Shared - Message Building
    // -------------------------------------------------------

    protected function buildHistoryMessages(Collection $history): array
    {
        return $history->map(function ($msg) {
            return [
                'role'    => $msg->sender_type === MessageSenderType::CUSTOMER ? 'user' : 'assistant',
                'content' => $msg->body ?? '',
            ];
        })->values()->toArray();
    }

    protected function buildUserTurn(
        string $newMessage,
        array $imageUrls,
        array $fileNames,
        array $extractedContent
    ): array {
        return [
            'role'    => 'user',
            'content' => $this->buildContentBlocks($newMessage, $imageUrls, $fileNames, $extractedContent),
        ];
    }

    protected function buildContentBlocks(
        string $newMessage,
        array $imageUrls,
        array $fileNames,
        array $extractedContent
    ): array {
        $blocks   = array_merge([], $this->buildImageBlocks($imageUrls));
        $blocks[] = $this->buildTextBlock($newMessage, $fileNames, $extractedContent);

        return $blocks;
    }

    protected function buildTextBlock(
        string $newMessage,
        array $fileNames,
        array $extractedContent
    ): array {
        return [
            'type' => 'text',
            'text' => MessageContextBuilder::buildTextContent($newMessage, $fileNames, $extractedContent),
        ];
    }

    // -------------------------------------------------------
    // Shared - Error Handling
    // -------------------------------------------------------

    protected function logError($response, string $provider): void
    {
        Log::error("{$provider} API error", [
            'status'   => $response->status(),
            'response' => $response->body(),
        ]);
    }

    protected function escalationFallback(): string
    {
        return 'I\'m sorry, I\'m having trouble responding right now. '
             . 'Let me connect you with a human agent. [ESCALATE]';
    }
}