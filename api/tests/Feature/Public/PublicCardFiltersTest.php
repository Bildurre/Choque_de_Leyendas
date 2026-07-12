<?php

// GET /api/cards/filters — opciones de los selects de filtro del índice.

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    ensurePublicApiRoutes();
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

it('devuelve facciones publicadas y todos los tipos con nombres localizados', function () {
    $faction = publicFaction();
    publicFaction(['name' => ['es' => 'Sin publicar', 'en' => 'Unpublished'], 'is_published' => false]);
    $weapon = publicCardType(['name' => ['es' => 'Arma', 'en' => 'Weapon']]);
    $technique = publicCardType(); // 'Técnica' / 'Technique'

    $response = $this->getJson('/api/cards/filters')->assertOk();

    // Facciones: solo publicadas; nombres YA localizados (string, no mapa)
    $response->assertJsonCount(1, 'factions');
    expect($response->json('factions.0'))->toBe(['id' => $faction->id, 'name' => 'Alianza'])
        // Tipos: todos (no tienen publicación), ordenados por nombre (es)
        ->and($response->json('types'))->toBe([
            ['id' => $weapon->id, 'name' => 'Arma'],
            ['id' => $technique->id, 'name' => 'Técnica'],
        ]);
});

it('localiza los nombres con ?locale', function () {
    publicFaction();
    publicCardType(['name' => ['es' => 'Arma', 'en' => 'Weapon']]);

    $response = $this->getJson('/api/cards/filters?locale=en')->assertOk();

    expect($response->json('factions.0.name'))->toBe('Alliance')
        ->and($response->json('types.0.name'))->toBe('Weapon');
});
