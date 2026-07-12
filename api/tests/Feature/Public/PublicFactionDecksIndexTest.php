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

it('filtra por game_mode_id', function () {
    $skirmish = publicGameMode();
    $deck = publicDeck(['game_mode_id' => $skirmish->id]);
    publicDeck(['name' => ['es' => 'Otro mazo', 'en' => 'Other deck']]);

    $response = $this->getJson("/api/faction-decks?game_mode_id={$skirmish->id}")->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($deck->id);
});

it('filtra por faction_id: mazos que incluyan esa facción (pivot)', function () {
    $alianza = publicFaction();
    $horda = publicFaction(['name' => ['es' => 'Horda', 'en' => 'Horde'], 'color' => '#993333']);

    $deAlianza = publicDeck(['name' => ['es' => 'De la Alianza', 'en' => 'Alliance deck']]);
    $deAlianza->factions()->attach($alianza->id);

    $multi = publicDeck(['name' => ['es' => 'Multifacción', 'en' => 'Multifaction']]);
    $multi->factions()->attach([$alianza->id, $horda->id]);

    $soloHorda = publicDeck(['name' => ['es' => 'Solo Horda', 'en' => 'Horde only']]);
    $soloHorda->factions()->attach($horda->id);

    publicDeck(['name' => ['es' => 'Sin facciones', 'en' => 'Factionless']]);

    $response = $this->getJson("/api/faction-decks?faction_id={$alianza->id}")->assertOk();

    // Orden por defecto: nombre asc del locale (es)
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$deAlianza->id, $multi->id]);
});

it('ordena con el contrato de sort, incluido oldest', function () {
    $bravo = publicDeck(['name' => ['es' => 'Bravo', 'en' => 'Bravo']]);
    $alfa = publicDeck(['name' => ['es' => 'Alfa', 'en' => 'Alpha']]);

    // Sin sort: orden histórico, nombre asc del locale
    $default = $this->getJson('/api/faction-decks')->assertOk();
    expect(collect($default->json('data'))->pluck('id')->all())->toBe([$alfa->id, $bravo->id]);

    $oldest = $this->getJson('/api/faction-decks?sort=oldest')->assertOk();
    expect(collect($oldest->json('data'))->pluck('id')->all())->toBe([$bravo->id, $alfa->id]);

    $latest = $this->getJson('/api/faction-decks?sort=latest')->assertOk();
    expect(collect($latest->json('data'))->pluck('id')->all())->toBe([$alfa->id, $bravo->id]);

    $desc = $this->getJson('/api/faction-decks?sort=name_desc')->assertOk();
    expect(collect($desc->json('data'))->pluck('id')->all())->toBe([$bravo->id, $alfa->id]);
});
