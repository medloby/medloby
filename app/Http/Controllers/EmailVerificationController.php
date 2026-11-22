<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function verify(
        Request $request,
        int $id,
        string $hash
    ): JsonResponse|RedirectResponse {
        $user = User::findOrFail($id);
        abort_if($user->email === null || $user->clinic_onboarding_pending, 403, 'Bu hesap için e-posta doğrulaması kullanılamıyor.');

        if (! hash_equals(
            (string) $hash,
            sha1($user->getEmailForVerification())
        )) {
            abort(403, 'Geçersiz doğrulama bağlantısı.');
        }

        $verified = $user->hasVerifiedEmail();

        if (! $verified && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($request->prefers(['application/json', 'text/html']) === 'text/html') {
            return redirect()->route('package-offers', ['email_verified' => 1]);
        }

        return response()->json([
            'success' => true,
            'message' => $verified
                ? 'E-posta adresi zaten doğrulanmış.'
                : 'E-posta adresi başarıyla doğrulandı.',
        ]);
    }

    public function resend(Request $request): JsonResponse
    {
        abort_if($request->user()->email === null || $request->user()->clinic_onboarding_pending, 403, 'Bu hesap için e-posta doğrulaması kullanılamıyor.');
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'E-posta adresi zaten doğrulanmış.',
            ]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Doğrulama e-postası gönderildi.',
        ]);
    }
}
