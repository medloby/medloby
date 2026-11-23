<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('the homepage links to every policy and each policy opens as a draft', function () {
    $home = $this->get('/')->assertOk()->assertSeeText('Politikalar');

    foreach ([
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
    ] as $slug => $title) {
        $home->assertSee(route('policies.show', $slug))->assertSeeText($title);
        $this->get('/politikalar/'.$slug)->assertOk()->assertSeeText($title)
            ->assertSeeText('Yayın öncesi taslak')->assertSee('content="noindex"', false)
            ->assertSee(route('home').'#politikalar');
    }

    $this->get('/politikalar/bilinmeyen')->assertNotFound();
});

test('the kvkk overview links the existing notices only when the operator has published them', function () {
    config([
        'medloby.policies_published' => true,
        'medloby.privacy_published' => true,
        'medloby.operator.legal_name' => 'Örnek İşletmeci',
        'medloby.operator.postal_address' => '',
        'medloby.operator.support_email' => 'destek@example.test',
        'medloby.operator.editor_email' => 'editor@example.test',
    ]);

    $path = '/politikalar/kvkk';
    $this->get($path)->assertOk()->assertSeeText('Yayın öncesi taslak')
        ->assertDontSee(route('privacy.notice', 'hasta'));

    config(['medloby.operator.postal_address' => 'Örnek Posta Adresi']);
    $this->get($path)->assertOk()->assertDontSeeText('Yayın öncesi taslak')->assertDontSee('content="noindex"', false)
        ->assertSee(route('privacy.notice', 'hasta'))->assertSee(route('privacy.notice', 'klinik'))
        ->assertSeeText('Örnek İşletmeci')->assertSeeText('Örnek Posta Adresi');
});

test('the cookie policy reflects the configured web session settings', function () {
    config(['session.cookie' => 'test_web_oturumu', 'session.lifetime' => 42]);

    $this->get('/politikalar/cerez-politikasi')->assertOk()->assertSeeText('test_web_oturumu')
        ->assertSeeText('42 dakikadır')->assertSeeText('XSRF-TOKEN')->assertSeeText('medloby-pending-package');
});
