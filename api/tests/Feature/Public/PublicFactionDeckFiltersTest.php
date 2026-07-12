<?php

// GET /api/faction-decks/filters — opciones de los selects de filtro del índice.

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    ensurePublicApiRoutes();
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

it('devuelve modos de juego y facciones publicadas (con color) localizados', function () {
    $mode = publicGameMode(); // 'Escaramuza' / 'Skirmish'
    $faction = publicFaction();
    publicFaction(['name' => ['es' => 'Sin publicar', 'en' => 'Unpublished'], 'is_published' => false]);

    $response = $this->getJson('/api/faction-decks/filters')->assertOk();

    expect($response->json('modes'))->toBe([['id' => $mode->id, 'name' => 'Escaramuza']])
        ->and($response->json('factions'))->toBe([[
            'id' => $faction->id,
            'name' => 'Alianza',
            'color' => '#336699',
        ]]);
});

it('localiza los nombres con ?locale', function () {
    publicGameMode();
    publicFaction();

    $response = $this->getJson('/api/faction-decks/filters?locale=en')->assertOk();

    expect($response->json('modes.0.name'))->toBe('Skirmish')
        ->and($response->json('factions.0.name'))->toBe('Alliance');
});

it('no exige autenticación', function () {
    $this->getJson('/api/faction-decks/filters')->assertOk();
});
