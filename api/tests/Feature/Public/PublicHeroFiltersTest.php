<?php

// GET /api/heroes/filters — opciones de los selects de filtro del índice.

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    ensurePublicApiRoutes();
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

it('devuelve facciones publicadas (con color), clases, superclases y razas localizadas', function () {
    $faction = publicFaction();
    publicFaction(['name' => ['es' => 'Sin publicar', 'en' => 'Unpublished'], 'is_published' => false]);
    $class = publicHeroClass(); // 'Guerrero' con superclase 'Luchador'
    $race = publicHeroRace(); // 'Humano' / 'Human'

    $response = $this->getJson('/api/heroes/filters')->assertOk();

    // Facciones: solo publicadas, con color para pintar el filtro
    $response->assertJsonCount(1, 'factions');
    expect($response->json('factions.0'))->toBe([
        'id' => $faction->id,
        'name' => 'Alianza',
        'color' => '#336699',
    ])
        // Clases con su superclase, para poder filtrar en cascada en el front
        ->and($response->json('classes'))->toBe([[
            'id' => $class->id,
            'name' => 'Guerrero',
            'superclass_id' => $class->hero_superclass_id,
        ]])
        ->and($response->json('superclasses'))->toBe([[
            'id' => $class->hero_superclass_id,
            'name' => 'Luchador',
        ]])
        ->and($response->json('races'))->toBe([['id' => $race->id, 'name' => 'Humano']]);
});

it('localiza los nombres con ?locale', function () {
    publicFaction();
    publicHeroClass();
    publicHeroRace();

    $response = $this->getJson('/api/heroes/filters?locale=en')->assertOk();

    expect($response->json('factions.0.name'))->toBe('Alliance')
        ->and($response->json('classes.0.name'))->toBe('Warrior')
        ->and($response->json('superclasses.0.name'))->toBe('Fighter')
        ->and($response->json('races.0.name'))->toBe('Human');
});

it('no exige autenticación', function () {
    $this->getJson('/api/heroes/filters')->assertOk();
});
