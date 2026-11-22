<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

class AccountVerificationNotification extends VerifyEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function shouldSend(User $user, string $channel): bool
    {
        return filled($user->email) && ! $user->hasVerifiedEmail() && ! $user->clinic_onboarding_pending;
    }
}
