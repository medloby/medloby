<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        [$user, $token] = DB::transaction(function () use ($validated): array {
            $user = User::query()->where('email', $validated['email'])->lockForUpdate()->first();

            if (! $user || ! Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['E-posta adresi veya şifre hatalı.'],
                ]);
            }

            $user->tokens()->delete();

            return [$user, $user->createToken('medloby-auth')->plainTextToken];
        }, attempts: 3);

        return response()->json([
            'success' => true,
            'message' => 'Giriş başarılı.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->load([
            'businessMemberships.business',
        ]);
        $user->loadExists('patientProfile');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Çıkış başarılı.',
        ]);
    }
}
