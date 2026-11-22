<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\AccountPasswordResetNotification;
use App\Rules\AccountPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function requestForm(): Response
    {
        return $this->form();
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $email = $request->validate(['email' => ['required', 'email', 'max:255']], [
            'email.required' => 'E-posta adresinizi yazın.',
            'email.email' => 'Geçerli bir e-posta adresi yazın.',
            'email.max' => 'E-posta adresi en fazla 255 karakter olabilir.',
        ])['email'];

        DB::transaction(function () use ($email): void {
            $accountEmail = User::query()->where('email', $email)->lockForUpdate()->value('email');
            $lowercaseEmail = Str::lower($email);
            if ($accountEmail === null && $email !== $lowercaseEmail) {
                $accountEmail = User::query()->where('email', $lowercaseEmail)->lockForUpdate()->value('email');
            }

            Password::sendResetLink(['email' => $accountEmail ?? $email, 'clinic_onboarding_pending' => false], function (User $user, string $token): void {
                $user->notify((new AccountPasswordResetNotification($token))->afterCommit());
            });
        }, attempts: 3);

        return redirect()->route('password.request')
            ->with('status', 'Bu adresle bir Medloby hesabı varsa şifre yenileme bağlantısı e-posta ile gönderilecek.');
    }

    public function resetForm(Request $request): Response
    {
        $link = Validator::make($request->query(), $this->linkRules());

        if ($link->fails()) {
            return $this->invalidLink();
        }

        return $this->form($link->validated());
    }

    public function reset(Request $request): Response|RedirectResponse
    {
        $link = Validator::make($request->post(), $this->linkRules());
        if ($link->fails()) {
            return $this->invalidLink();
        }

        $password = Validator::make($request->post(), [
            'password' => ['bail', 'required', 'string', 'confirmed', new AccountPassword],
        ], [
            'password.required' => 'Yeni şifrenizi yazın.',
            'password.string' => 'Geçerli bir şifre yazın.',
            'password.confirmed' => 'Yazdığınız şifreler aynı olmalıdır.',
        ]);

        $link = $link->validated();
        if ($password->fails()) {
            return $this->form($link, message: $password->errors()->first('password'), status: 422);
        }

        $credentials = $link + $password->validated() + ['clinic_onboarding_pending' => false];
        $result = DB::transaction(function () use ($credentials): string {
            User::query()->where('email', $credentials['email'])->lockForUpdate()->value('id');

            return Password::reset($credentials, function (User $user, string $password): void {
                if ($user->temporary_password_expires_at !== null) {
                    $user->email_verified_at ??= now();
                    $user->temporary_password_expires_at = null;
                }
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                DB::afterCommit(fn () => event(new PasswordReset($user)));
            });
        }, attempts: 3);

        if ($result !== Password::PASSWORD_RESET) {
            return $this->invalidLink();
        }

        return redirect()->route('package-offers')->withFragment('gorusmeler')
            ->with('password_reset', 'Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz.');
    }

    /** @return array{email: list<string>, token: list<string>} */
    private function linkRules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'],
        ];
    }

    private function invalidLink(): Response
    {
        return $this->form(message: 'Şifre yenileme bağlantısı geçersiz veya süresi dolmuş. Yeni bir bağlantı isteyin.', status: 422);
    }

    /** @param array{email: string, token: string}|null $link */
    private function form(?array $link = null, ?string $message = null, int $status = 200): Response
    {
        return response()->view('password-reset', compact('link', 'message'), $status, [
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
