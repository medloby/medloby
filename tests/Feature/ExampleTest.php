<?php

test('the application returns a successful response', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Tedavi paketleri ve klinik bilgileri')
        ->assertSee(route('package-offers').'#katalog')
        ->assertSee(route('package-offers').'#clinic-registration')
        ->assertSee(route('package-offers').'#gorusmeler');
});
