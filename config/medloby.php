<?php

return [
    'release_version' => env('MEDLOBY_RELEASE_VERSION'),
    'privacy_published' => env('MEDLOBY_PRIVACY_PUBLISHED', false),
    'policies_published' => env('MEDLOBY_POLICIES_PUBLISHED', false),
    'policy_preview' => env('MEDLOBY_POLICY_PREVIEW', false),
    'consent_template_preview' => env('MEDLOBY_CONSENT_TEMPLATE_PREVIEW', false),
    'search_indexing' => env('MEDLOBY_SEARCH_INDEXING', false),

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

    'preview_operator' => [
        'legal_name' => 'Medloby Demo İşletmesi (kurgu)',
        'postal_address' => 'Örnek Mahallesi, Prova Sokak No: 12, Demo İlçe (kurgu adres)',
        'privacy_email' => 'kvkk@medloby-demo.invalid',
        'privacy_kep' => 'basvuru@kep.medloby-demo.invalid',
        'editor_email' => 'editor@medloby-demo.invalid',
        'support_email' => 'destek@medloby-demo.invalid',
    ],
];
