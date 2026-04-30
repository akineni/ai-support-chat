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
 *
 * Endpoints for customers to start and participate in support conversations.
 * No authentication required — customers are identified by their session_token.
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
     * Creates a new support conversation and returns a session_token
     * the customer must include in all subsequent requests.
     *
     * @unauthenticated
     *
     * @bodyParam customer_name string required The customer's display name. Example: John Doe
     * @bodyParam customer_email string optional The customer's email address. Example: john@example.com
     *
     * @response 201 scenario="Success" {
     *   "status": "success",
     *   "message": "Conversation started",
     *   "data": {
     *     "session_token": "550e8400-e29b-41d4-a716-446655440000",
     *     "conversation": {
     *       "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *       "customer_name": "John Doe",
     *       "customer_email": "john@example.com",
     *       "status": "open",
     *       "mode": "ai",
     *       "assigned_agent": null,
     *       "taken_over_at": null,
     *       "created_at": "2025-01-01T00:00:00.000000Z"
     *     }
     *   }
     * }
     *
     * @response 422 scenario="Validation error" {
     *   "status": "error",
     *   "message": "The customer name field is required.",
     *   "errors": {
     *     "customer_name": ["The customer name field is required."]
     *   }
     * }
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
     * Returns paginated message history for a conversation.
     * Messages are ordered oldest to newest.
     *
     * @unauthenticated
     *
     * @urlParam sessionToken string required The session token received when starting the conversation. Example: 550e8400-e29b-41d4-a716-446655440000
     *
     * @response 200 scenario="Success" {
     *   "status": "success",
     *   "message": "Messages retrieved",
     *   "data": [
     *     {
     *       "sender_type": "customer",
     *       "body": "Hello, I need help with my order.",
     *       "is_read": true,
     *       "attachments": [],
     *       "created_at": "2025-01-01T00:00:00.000000Z"
     *     },
     *     {
     *       "sender_type": "ai",
     *       "body": "Hi John! I'd be happy to help with your order. Could you provide your order number?",
     *       "is_read": true,
     *       "attachments": [],
     *       "created_at": "2025-01-01T00:00:05.000000Z"
     *     },
     *     {
     *       "sender_type": "customer",
     *       "body": "Sure! My order number is #12345",
     *       "is_read": false,
     *       "attachments": [
     *         {
     *           "file_url": "https://res.cloudinary.com/demo/image/upload/sample.jpg",
     *           "file_name": "receipt.jpg",
     *           "file_type": "image/jpeg",
     *           "file_extension": "jpg",
     *           "file_size": 204800,
     *           "is_image": true,
     *           "created_at": "2025-01-01T00:00:10.000000Z"
     *         }
     *       ],
     *       "created_at": "2025-01-01T00:00:10.000000Z"
     *     }
     *   ],        Route::get('/{sessionToken}/messages', [Customer\ChatController::class, 'history']);

     *   "meta": {
     *     "current_page": 1,
     *     "from": 1,
     *     "last_page": 1,
     *     "path": "http://localhost:8000/api/v1/chat/550e8400/messages",
     *     "per_page": 50,
     *     "to": 3,
     *     "total": 3
     *   },
     *   "links": {
     *     "first": "http://localhost:8000/api/v1/chat/550e8400/messages?page=1",
     *     "last": "http://localhost:8000/api/v1/chat/550e8400/messages?page=1",
     *     "next": null,
     *     "prev": null
     *   }
     * }
     *
     * @response 404 scenario="Conversation not found" {
     *   "status": "error",
     *   "message": "Conversation not found."
     * }
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
     * Sends a customer message. If the conversation is in AI mode, Claude will
     * automatically reply. If in human mode, the assigned agent will reply manually.
     * Supports text with emojis and up to 5 file attachments.
     *
     * @unauthenticated
     *
     * @urlParam sessionToken string required The session token received when starting the conversation. Example: 550e8400-e29b-41d4-a716-446655440000
     *
     * @bodyParam body string optional The message text (required if no attachments). Example: Hi, I need help with my order #12345 😊
     * @bodyParam attachments file[] optional Up to 5 files. Allowed: jpg, jpeg, png, gif, webp, pdf, doc, docx, txt, zip. Max 10MB each.
     *
     * @response 200 scenario="AI reply (default)" {
     *   "status": "success",
     *   "message": "Message sent",
     *   "data": {
     *     "sender_type": "ai",
     *     "body": "Thanks John! I can see order #12345 in our system.",
     *     "is_read": false,
     *     "attachments": [],
     *     "created_at": "2025-01-01T00:00:15.000000Z"
     *   }
     * }
     *
     * @response 200 scenario="Human mode (agent will reply separately)" {
     *   "status": "success",
     *   "message": "Message sent",
     *   "data": {
     *     "sender_type": "customer",
     *     "body": "I want to speak to a real person please.",
     *     "is_read": false,
     *     "attachments": [],
     *     "created_at": "2025-01-01T00:00:20.000000Z"
     *   }
     * }
     *
     * @response 200 scenario="Message with attachment" {
     *   "status": "success",
     *   "message": "Message sent",
     *   "data": {
     *     "sender_type": "ai",
     *     "body": "I can see the receipt you shared. Let me look into this charge for you.",
     *     "is_read": false,
     *     "attachments": [],
     *     "created_at": "2025-01-01T00:00:25.000000Z"
     *   }
     * }
     *
     * @response 404 scenario="Conversation not found" {
     *   "status": "error",
     *   "message": "Conversation not found."
     * }
     *
     * @response 422 scenario="Conversation is closed" {
     *   "status": "error",
     *   "message": "This conversation is closed."
     * }
     *
     * @response 422 scenario="Validation error" {
     *   "status": "error",
     *   "message": "A message body is required when no attachments are provided.",
     *   "errors": {
     *     "body": ["A message body is required when no attachments are provided."]
     *   }
     * }
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
