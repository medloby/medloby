<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;
use SensitiveParameter;

final class AccountPassword implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, #[SensitiveParameter] mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) > 72 || str_contains($value, "\0")) {
            $fail('Şifre çok uzun veya geçersiz bir karakter içeriyor.');

            return;
        }

        if (Str::length($value) < 8) {
            $fail('Şifreniz en az 8 karakter olmalıdır.');
        }
    }
}
