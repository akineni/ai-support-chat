<?php

namespace App\Http\Controllers\Agent;

use App\Enums\MessageSenderType;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AgentReplyRequest;
use App\Http\Requests\SearchFilterRequest;
use App\Http\Resources\ApiCollection;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Services\ConversationService;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Agent Chat
 */
class ChatController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
        protected MessageService      $messageService,
    ) {}

    /**
     * List all conversations
     *
     * @queryParam status string Filter by status. Accepted: open, pending_handover, closed. Example: pending_handover
     * @queryParam per_page int Items per page. Default: 20. Example: 10
     *
     * @response 200 {"status":"success","message":"Conversations retrieved","data":[{"uuid":"a1b2c3d4-e5f6-7890-abcd-ef1234567890","customer_name":"John Doe","customer_email":"john@example.com","status":"open","mode":"ai","assigned_agent":null,"taken_over_at":null,"created_at":"2025-01-01T00:00:00.000000Z"}],"meta":{"current_page":1,"per_page":20,"total":1}}
     * @response 401 {"status":"error","message":"Unauthenticated."}
     * @response 422 {"status":"error","message":"Invalid status value provided."}
     */
    public function index(SearchFilterRequest $request): JsonResponse
    {
        $conversations = $this->conversationService->getAllConversationsForAgent(
            $request->status(),
            $request->perPage() ?? 20
        );

        return ApiResponse::success(
            'Conversations retrieved',
            ApiCollection::for($conversations, ConversationResource::class)
        );
    }

    /**
     * Get conversation messages
     *
     * @urlParam uuid string required The conversation UUID. Example: a1b2c3d4-e5f6-7890-abcd-ef1234567890
     *
     * @response 200 {"status":"success","message":"Messages retrieved","data":[{"sender_type":"customer","body":"Hello, I need help with my order.","is_read":true,"attachments":[],"created_at":"2025-01-01T00:00:00.000000Z"},{"sender_type":"ai","body":"Hi! I'd be happy to help. What is your order number?","is_read":true,"attachments":[],"created_at":"2025-01-01T00:00:05.000000Z"}],"meta":{"current_page":1,"per_page":50,"total":2}}
     * @response 401 {"status":"error","message":"Unauthenticated."}
     * @response 404 {"status":"error","message":"Conversation not found"}
     */
    public function messages(string $uuid): JsonResponse
    {
        $messages = $this->conversationService->getConversationHistoryForAgent($uuid);

        return ApiResponse::success(
            'Messages retrieved',
            ApiCollection::for($messages, MessageResource::class)
        );
    }

    /**
     * Take over a conversation
     *
     * Switches conversation from AI to human mode and assigns it to the authenticated agent.
     *
     * @urlParam uuid string required The conversation UUID. Example: a1b2c3d4-e5f6-7890-abcd-ef1234567890
     *
     * @response 200 {"status":"success","message":"Conversation taken over","data":{"uuid":"a1b2c3d4-e5f6-7890-abcd-ef1234567890","customer_name":"John Doe","customer_email":"john@example.com","status":"open","mode":"human","assigned_agent":{"id":1,"name":"Sarah Support"},"taken_over_at":"2025-01-01T00:05:00.000000Z","created_at":"2025-01-01T00:00:00.000000Z"}}
     * @response 401 {"status":"error","message":"Unauthenticated."}
     * @response 404 {"status":"error","message":"Conversation not found"}
     * @response 422 {"status":"error","message":"Conversation is already assigned to an agent"}
     */
    public function takeover(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->conversationService->agentTakeover($uuid, $request->user()->id);

        return ApiResponse::success(
            'Conversation taken over',
            new ConversationResource($conversation->load('assignedAgent'))
        );
    }

    /**
     * Release conversation back to AI
     *
     * @urlParam uuid string required The conversation UUID. Example: a1b2c3d4-e5f6-7890-abcd-ef1234567890
     *
     * @response 200 {"status":"success","message":"Conversation released back to AI","data":{"uuid":"a1b2c3d4-e5f6-7890-abcd-ef1234567890","customer_name":"John Doe","customer_email":"john@example.com","status":"open","mode":"ai","assigned_agent":null,"taken_over_at":null,"created_at":"2025-01-01T00:00:00.000000Z"}}
     * @response 401 {"status":"error","message":"Unauthenticated."}
     * @response 404 {"status":"error","message":"Conversation not found"}
     * @response 422 {"status":"error","message":"Conversation is already in AI mode"}
     */
    public function release(string $uuid): JsonResponse
    {
        $conversation = $this->conversationService->releaseToAi($uuid);

        return ApiResponse::success(
            'Conversation released back to AI',
            new ConversationResource($conversation)
        );
    }

    /**
     * Reply to a customer
     *
     * Conversation must be in human mode first.
     *
     * @urlParam uuid string required The conversation UUID. Example: a1b2c3d4-e5f6-7890-abcd-ef1234567890
     * @bodyParam body string required The agent's reply. Example: Hi John! I can see your order #12345 right here.
     *
     * @response 200 {"status":"success","message":"Reply sent","data":{"sender_type":"agent","body":"Hi John! I can see your order #12345 right here.","is_read":false,"attachments":[],"created_at":"2025-01-01T00:10:00.000000Z"}}
     * @response 401 {"status":"error","message":"Unauthenticated."}
     * @response 404 {"status":"error","message":"Conversation not found"}
     * @response 422 {"status":"error","message":"Take over the conversation before replying"}
     */
    public function reply(AgentReplyRequest $request, string $uuid): JsonResponse
    {
        $message = $this->messageService->handleAgentReply(
            $uuid,
            $request->user()->id,
            $request->input('body')
        );

        return ApiResponse::success(
            'Reply sent',
            new MessageResource($message)
        );
    }

    /**
     * Broadcast agent typing status
     *
     * @urlParam uuid string required Example: a1b2c3d4-e5f6-7890-abcd-ef1234567890
     * @bodyParam is_typing bool required Example: true
     */
    public function typing(Request $request, string $uuid): JsonResponse
    {
        $this->conversationService->broadcastTyping(
            $uuid,
            MessageSenderType::AGENT->value,
            (bool) $request->input('is_typing', false),
            true
        );

        return ApiResponse::success('OK');
    }

    /**
     * Get unread message counts per conversation
     */
    public function unreadCounts(): JsonResponse
    {
        $counts = $this->conversationService->getUnreadCounts();

        return ApiResponse::success('Unread counts retrieved successfully', $counts);
    }
}
