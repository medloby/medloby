<?php

return [
    'privacy_published' => env('MEDLOBY_PRIVACY_PUBLISHED', false),

    'operator' => [
        'legal_name' => env('MEDLOBY_OPERATOR_LEGAL_NAME'),
        'postal_address' => env('MEDLOBY_OPERATOR_POSTAL_ADDRESS'),
        'privacy_email' => env('MEDLOBY_PRIVACY_EMAIL'),
        'privacy_kep' => env('MEDLOBY_PRIVACY_KEP'),
        'editor_email' => env('MEDLOBY_EDITOR_EMAIL'),
        'support_email' => env('MEDLOBY_SUPPORT_EMAIL'),
    ],
];
