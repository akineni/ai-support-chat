<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Authentication
 *
 * Endpoints for agent authentication.
 */
class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
    ) {}

    /**
     * Agent login
     *
     * Authenticates an agent and returns a Sanctum bearer token
     * to be used in all subsequent agent requests.
     *
     * @unauthenticated
     *
     * @bodyParam email string required The agent's email address. Example: sarah@support.com
     * @bodyParam password string required The agent's password. Example: password
     *
     * @response 200 scenario="Success" {
     *   "status": "success",
     *   "message": "Login successful",
     *   "data": {
     *     "token": "1|laravel_sanctum_token_here",
     *     "user": {
     *       "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
     *       "name": "Sarah Support",
     *       "email": "sarah@support.com"
     *     }
     *   }
     * }
     *
     * @response 401 scenario="Invalid credentials" {
     *   "status": "error",
     *   "message": "Invalid credentials"
     * }
     *
     * @response 422 scenario="Validation error" {
     *   "status": "error",
     *   "message": "The email field is required.",
     *   "errors": {
     *     "email": ["The email field is required."]
     *   }
     * }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->only('email', 'password'));

        if (!$result['success']) {
            return ApiResponse::error('Invalid credentials', 401);
        }

        return ApiResponse::success('Login successful', [
            'token' => $result['token'],
            'user'  => new UserResource($result['user']),
        ]);
    }

    /**
     * Agent logout
     *
     * Revokes the current agent's bearer token.
     *
     * @response 200 scenario="Success" {
     *   "status": "success",
     *   "message": "Logged out successfully"
     * }
     *
     * @response 401 scenario="Unauthenticated" {
     *   "status": "error",
     *   "message": "Unauthenticated."
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success('Logged out successfully');
    }
}