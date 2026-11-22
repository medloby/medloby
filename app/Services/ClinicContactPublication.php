<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ClinicContactPublication
{
    public const array BRANCH_FIELDS = ['phone' => 'Telefon', 'email' => 'İletişim e-postası'];

    public const array CLINIC_FIELDS = [...self::BRANCH_FIELDS, 'website' => 'Web sitesi'];

    /** @return array<string, array{href: string, text: string}> */
    public function links(Business|Branch $profile): array
    {
        $selected = $profile->public_contact_fields;
        if (! is_array($selected)) {
            return [];
        }

        $fields = $profile instanceof Business ? self::CLINIC_FIELDS : self::BRANCH_FIELDS;
        $links = [];
        foreach ($fields as $field => $label) {
            if (! in_array($field, $selected, true)) {
                continue;
            }

            $value = $profile->{$field};
            $href = $this->href($field, $value);
            if ($href !== null) {
                $links[$field] = ['href' => $href, 'text' => $field === 'website' ? $label : trim($value)];
            }
        }

        return $links;
    }

    public function href(string $field, ?string $value): ?string
    {
        $value = trim($value ?? '');

        return match ($field) {
            'phone' => $this->phoneHref($value),
            'email' => Validator::make(['email' => $value], ['email' => ['required', 'email']])->passes()
                ? 'mailto:'.str_replace('%40', '@', rawurlencode($value)) : null,
            'website' => Str::isUrl($value, ['http', 'https']) ? $value : null,
            default => null,
        };
    }

    private function phoneHref(string $phone): ?string
    {
        $number = preg_replace('/[\s().-]+/u', '', $phone);

        return $number !== null && preg_match('/\A\+?[0-9]+\z/', $number) ? 'tel:'.$number : null;
    }
}
