<?php

test('draft privacy notices and links stay hidden until publication and identity are set', function () {
    config([
        'medloby.privacy_published' => false,
        'medloby.operator.legal_name' => 'Örnek Medloby İşletmecisi',
        'medloby.operator.postal_address' => 'Örnek posta adresi',
    ]);

    $this->get('/kvkk/hasta')->assertNotFound();
    $this->get('/paket-teklifleri')->assertOk()->assertDontSee(route('privacy.notice', 'hasta'));

    config(['medloby.privacy_published' => true, 'medloby.operator.postal_address' => '  ']);
    $this->get('/kvkk/klinik')->assertNotFound();
    $this->get('/')->assertOk()->assertDontSee(route('privacy.notice', 'klinik'));
});

test('published notices explain the two registration flows and display the configured channels', function () {
    config([
        'medloby.privacy_published' => true,
        'medloby.operator.legal_name' => 'Örnek Medloby İşletmecisi',
        'medloby.operator.postal_address' => 'Örnek posta adresi',
        'medloby.operator.privacy_email' => 'kvkk@example.test',
        'medloby.operator.privacy_kep' => 'geçersiz adres',
        'medloby.operator.editor_email' => 'editor@example.test',
        'medloby.operator.support_email' => 'destek@example.test',
    ]);

    $this->get('/kvkk/hasta')->assertOk()
        ->assertSeeText('Hasta hesabı KVKK aydınlatma metni')
        ->assertSeeText('Örnek Medloby İşletmecisi')
        ->assertSeeText('Örnek posta adresi')
        ->assertSee('mailto:kvkk@example.test')
        ->assertSeeText('30 gün')
        ->assertDontSeeText('geçersiz adres')
        ->assertDontSeeText('Sözleşmenin sürümü, kabul zamanı');
    $this->get('/kvkk/klinik')->assertOk()
        ->assertSeeText('Klinik başvurusu ve hesabı KVKK aydınlatma metni')
        ->assertSeeText('Sözleşmenin sürümü, kabul zamanı')
        ->assertSeeText('IP adresi');
    $this->get('/kvkk/baska')->assertNotFound();
    $this->get('/')->assertOk()
        ->assertSee(route('privacy.notice', 'hasta'))
        ->assertSee(route('privacy.notice', 'klinik'))
        ->assertSee('mailto:editor@example.test')
        ->assertSee('mailto:destek@example.test');
    $this->get('/paket-teklifleri')->assertOk()
        ->assertSeeInOrder(['Hasta hesabı KVKK aydınlatma metni', 'Hasta hesabını oluştur'])
        ->assertSeeInOrder(['Klinik başvurusu KVKK aydınlatma metni', 'klinik üyelik sözleşmesini okudum ve kabul ediyorum']);
});
