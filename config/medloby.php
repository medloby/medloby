<?php

return [
    'release_version' => env('MEDLOBY_RELEASE_VERSION'),
    'privacy_published' => env('MEDLOBY_PRIVACY_PUBLISHED', false),
    'policies_published' => env('MEDLOBY_POLICIES_PUBLISHED', false),

    'policy_pages' => [
        'hizmet-sartlari' => 'Hizmet Şartları',
        'kullanim-sartlari-ve-kosullari' => 'Kullanım Şartları ve Koşulları',
        'gizlilik-bildirimi' => 'Gizlilik Bildirimi',
        'kvkk' => 'KVKK',
        'cerez-politikasi' => 'Çerez Politikası',
        'hasta-haklari' => 'Hasta Hakları',
        'tibbi-yasal-uyari' => 'Tıbbi Yasal Uyarı',
        'odeme-ve-depozito-sartlari' => 'Ödeme ve Depozito Şartları',
        'inceleme-ve-yorum-politikasi' => 'İnceleme ve Yorum Politikası',
        'finans-politikasi' => 'Finans Politikası',
        'yayin-politikasi' => 'Yayın Politikası',
    ],

    'operator' => [
        'legal_name' => env('MEDLOBY_OPERATOR_LEGAL_NAME'),
        'postal_address' => env('MEDLOBY_OPERATOR_POSTAL_ADDRESS'),
        'privacy_email' => env('MEDLOBY_PRIVACY_EMAIL'),
        'privacy_kep' => env('MEDLOBY_PRIVACY_KEP'),
        'editor_email' => env('MEDLOBY_EDITOR_EMAIL'),
        'support_email' => env('MEDLOBY_SUPPORT_EMAIL'),
    ],
];
