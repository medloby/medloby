<?php

namespace App\Services;

class PrivacyPublication
{
    public function isPublished(): bool
    {
        return ! $this->isPreviewing() && config('medloby.privacy_published') === true
            && $this->operator('legal_name') !== ''
            && $this->operator('postal_address') !== '';
    }

    public function isPreviewing(): bool
    {
        return app()->environment('local') && config('medloby.policy_preview') === true;
    }

    public function isVisible(): bool
    {
        return $this->isPreviewing() || $this->isPublished();
    }

    public function contactSettingsReady(): bool
    {
        return ! $this->isPreviewing() && $this->email('support_email') !== null
            && $this->email('editor_email') !== null
            && ($this->operator('privacy_email') === '' || $this->email('privacy_email') !== null)
            && ($this->operator('privacy_kep') === '' || $this->email('privacy_kep') !== null);
    }

    public function policiesArePublished(): bool
    {
        return config('medloby.policies_published') === true && $this->isPublished() && $this->contactSettingsReady();
    }

    public function operator(string $field): string
    {
        $operator = $this->isPreviewing() ? 'preview_operator' : 'operator';
        $value = config('medloby.'.$operator.'.'.$field);

        return is_string($value) ? trim($value) : '';
    }

    public function email(string $field): ?string
    {
        return filter_var($this->operator($field), FILTER_VALIDATE_EMAIL) ?: null;
    }
}
