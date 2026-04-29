<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function login(array $credentials): array
    {
        if (!Auth::attempt($credentials)) {
            return ['success' => false];
        }

        /** @var User $user */
        $user  = Auth::user();
        $token = $user->createToken('agent-token')->plainTextToken;

        return [
            'success' => true,
            'user'    => $user,
            'token'   => $token,
        ];
    }

    public function logout(User $user): void
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }
    }
}
