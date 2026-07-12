<?php

// GET /api/faction-decks — índice público de mazos.

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    ensurePublicApiRoutes();
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

it('lista solo mazos publicados con modo, facciones y totales publicados', function () {
    $faction = publicFaction();
    $draftFaction = publicFaction(['name' => ['es' => 'Horda'], 'color' => '#993333', 'is_published' => false]);
    $deck = publicDeck(['game_mode_id' => publicGameMode()->id]);
    $deck->factions()->attach([$faction->id, $draftFaction->id]);
    $deck->cards()->attach(publicCard()->id, ['copies' => 3]);
    $deck->cards()->attach(publicCard(['name' => ['es' => 'Borrador'], 'is_published' => false])->id, ['copies' => 2]);
    $deck->heroes()->attach(publicHero()->id);
    $deck->heroes()->attach(publicHero(['name' => ['es' => 'Borrador'], 'is_published' => false])->id);
    publicDeck(['name' => ['es' => 'Mazo borrador'], 'is_published' => false]);

    $response = $this->getJson('/api/faction-decks')->assertOk();

    $response->assertJsonCount(1, 'data');
    $item = $response->json('data.0');

    expect($item)->toMatchArray([
        'id' => $deck->id,
        'name' => 'Mazo inicial',
        'slug' => 'mazo-inicial',
        'icon' => null,
        // Solo cuenta el contenido publicado (regla dura de la web pública)
        'total_cards' => 3,
        'total_heroes' => 1,
    ])
        ->and($item['game_mode']['name'])->toBe('Escaramuza')
        // Las facciones sin publicar tampoco salen en el índice
        ->and($item['factions'])->toHaveCount(1)
        ->and($item['factions'][0])->toMatchArray(['name' => 'Alianza', 'slug' => 'alianza', 'color' => '#336699']);
});

it('localiza el índice con ?locale', function () {
    publicDeck(['game_mode_id' => publicGameMode()->id]);

    $response = $this->getJson('/api/faction-decks?locale=en')->assertOk();

    expect($response->json('data.0.name'))->toBe('Starter deck')
        ->and($response->json('data.0.slug'))->toBe('starter-deck')
        ->and($response->json('data.0.game_mode.name'))->toBe('Skirmish');
});
