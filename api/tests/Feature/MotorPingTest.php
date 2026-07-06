<?php

it('responde el ping del motor con versión y locales', function () {
    $this->getJson('/api/motor/ping')
        ->assertOk()
        ->assertJson([
            'package' => 'edc-motor/core',
            'version' => config('motor.version'),
            'default_locale' => 'es',
        ])
        ->assertJsonPath('locales', ['es', 'eu', 'en']);
});

it('lista los locales de contenido para los selectores', function () {
    $this->getJson('/api/locales')
        ->assertOk()
        ->assertJsonPath('default', 'es')
        ->assertJsonCount(3, 'locales')
        ->assertJsonPath('locales.1', ['code' => 'eu', 'name' => 'Euskara']);
});

it('fija el locale de la petición desde ?locale', function () {
    $this->getJson('/api/pages/nav?locale=eu');

    expect(app()->getLocale())->toBe('eu');
});

it('ignora locales no declarados', function () {
    $this->getJson('/api/pages/nav?locale=fr');

    expect(app()->getLocale())->toBe(config('app.locale'));
});
