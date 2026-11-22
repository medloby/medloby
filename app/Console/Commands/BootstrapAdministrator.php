<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\AccountPassword;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Console\Question\Question;

#[Signature('medloby:admin:bootstrap')]
#[Description('Medloby’nin ilk platform yöneticisini oluştur')]
final class BootstrapAdministrator extends Command
{
    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Yönetici kurulumu için etkileşimli bir terminal kullanın.');

            return self::FAILURE;
        }

        if ($this->administratorExists()) {
            $this->error('İlk platform yöneticisi zaten kurulmuş.');

            return self::FAILURE;
        }

        $validator = Validator::make([
            'name' => trim((string) $this->ask('Yöneticinin adı')),
            'email' => strtolower(trim((string) $this->ask('Yöneticinin e-posta adresi'))),
            'password' => $this->askPassword('Yönetici şifresi'),
            'password_confirmation' => $this->askPassword('Yönetici şifresini tekrar yazın'),
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['bail', 'required', 'string', 'confirmed', new AccountPassword],
        ], [
            'name.required' => 'Yöneticinin adını yazın.',
            'name.max' => 'Yöneticinin adı en fazla 255 karakter olabilir.',
            'email.required' => 'Yöneticinin e-posta adresini yazın.',
            'email.email' => 'Geçerli bir e-posta adresi yazın.',
            'email.max' => 'Yöneticinin e-posta adresi en fazla 255 karakter olabilir.',
            'password.required' => 'Yönetici şifresini yazın.',
            'password.confirmed' => 'Yazdığınız şifreler aynı olmalıdır.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $account = $validator->safe()->only(['name', 'email']);
        $account['password'] = Hash::make($validator->validated()['password']);

        try {
            $result = Cache::store('database')->lock('medloby:bootstrap-administrator', 60)->get(function () use ($account): int {
                if ($this->administratorExists()) {
                    $this->error('İlk platform yöneticisi zaten kurulmuş.');

                    return self::FAILURE;
                }

                if (User::query()->useWritePdo()->whereRaw('LOWER(email) = ?', [$account['email']])->exists()) {
                    $this->error('Bu e-posta adresi zaten kullanımda.');

                    return self::FAILURE;
                }

                (new User)->forceFill($account + ['is_admin' => true, 'email_verified_at' => now()])->save();

                $this->info('Platform yöneticisi oluşturuldu.');

                return self::SUCCESS;
            });
        } catch (UniqueConstraintViolationException) {
            $this->error('Bu e-posta adresi zaten kullanımda.');

            return self::FAILURE;
        }

        if ($result === false) {
            $this->error('Başka bir yönetici kurulumu çalışıyor.');

            return self::FAILURE;
        }

        return $result;
    }

    private function administratorExists(): bool
    {
        return User::query()->useWritePdo()->where('is_admin', true)->exists();
    }

    private function askPassword(string $prompt): ?string
    {
        $question = (new Question($prompt))->setHidden(true)->setHiddenFallback(false)->setTrimmable(false);
        $answer = $this->output->askQuestion($question);

        return $answer === null ? null : rtrim($answer, "\r\n");
    }
}
