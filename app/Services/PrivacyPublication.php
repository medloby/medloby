<?php

namespace App\Services;

class PrivacyPublication
{
    public function isPublished(): bool
    {
        return config('medloby.privacy_published') === true
            && $this->operator('legal_name') !== ''
            && $this->operator('postal_address') !== '';
    }

    public function operator(string $field): string
    {
        $value = config('medloby.operator.'.$field);

        return is_string($value) ? trim($value) : '';
    }

    public function email(string $field): ?string
    {
        return filter_var($this->operator($field), FILTER_VALIDATE_EMAIL) ?: null;
    }
}
