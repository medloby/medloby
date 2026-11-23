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

    public function contactSettingsReady(): bool
    {
        return $this->email('support_email') !== null
            && $this->email('editor_email') !== null
            && ($this->operator('privacy_email') === '' || $this->email('privacy_email') !== null)
            && ($this->operator('privacy_kep') === '' || $this->email('privacy_kep') !== null);
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
