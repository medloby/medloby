<?php

namespace App\Http\Controllers;

use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PatientRegistrationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:30'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:100'],
            'preferred_language' => ['nullable', 'string', 'max:10'],
            'preferred_currency' => ['nullable', 'string', 'size:3'],
        ]);

        $result = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => trim(
                    $validated['first_name'].' '.$validated['last_name']
                ),
                'email' => strtolower($validated['email']),
                'password' => Hash::make($validated['password']),
            ]);

            $patientProfile = PatientProfile::create([
                'user_id' => $user->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'country_code' => strtoupper(
                    $validated['country_code'] ?? 'TR'
                ),
                'city' => $validated['city'] ?? null,
                'preferred_language' => strtolower(
                    $validated['preferred_language'] ?? 'tr'
                ),
                'preferred_currency' => strtoupper(
                    $validated['preferred_currency'] ?? 'TRY'
                ),
                'status' => 'active',
            ]);

            $token = $user
                ->createToken('medloby-auth')
                ->plainTextToken;

            return [
                'user' => $user,
                'patient_profile' => $patientProfile,
                'token' => $token,
            ];
        });

        event(new Registered($result['user']));

        return response()->json([
            'success' => true,
            'message' => 'Hasta hesabı başarıyla oluşturuldu.',
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'user' => $result['user'],
                'patient_profile' => $result['patient_profile'],
            ],
        ], 201);
    }
}