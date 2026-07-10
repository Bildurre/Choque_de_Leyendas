<?php

// Locales dinámicos desde config: activar/desactivar un idioma en
// config/motor.php no debe romper estos tests.
it('responde el ping del motor con versión y locales', function () {
    $codes = array_keys(config('motor.locales'));

    $this->getJson('/api/motor/ping')
        ->assertOk()
        ->assertJsonStructure(['name', 'package', 'version', 'default_locale', 'locales'])
        ->assertJsonPath('default_locale', config('motor.default_locale'))
        ->assertJsonPath('locales', $codes);
});

it('lista los locales de contenido para los selectores', function () {
    $locales = config('motor.locales');

    $response = $this->getJson('/api/locales')
        ->assertOk()
        ->assertJsonPath('default', config('motor.default_locale'))
        ->assertJsonCount(count($locales), 'locales');

    foreach (array_keys($locales) as $i => $code) {
        $response->assertJsonPath("locales.{$i}.code", $code);
    }
});

it('fija el locale de la petición desde ?locale', function () {
    $code = array_keys(config('motor.locales'))[1] ?? config('motor.default_locale');
    $this->getJson('/api/pages/nav?locale='.$code);

    expect(app()->getLocale())->toBe($code);
});

it('cae al locale por defecto si llega uno desconocido', function () {
    $this->getJson('/api/pages/nav?locale=xx');

    expect(app()->getLocale())->toBe(config('motor.default_locale'));
});
