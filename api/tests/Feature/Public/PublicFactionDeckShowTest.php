<?php

// GET /api/faction-decks/{slug} — ficha pública de mazo con estadísticas.

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    ensurePublicApiRoutes();
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

it('muestra el mazo publicado con cartas (copias), héroes y estadísticas', function () {
    $faction = publicFaction();
    $type = publicCardType();
    $deck = publicDeck(['game_mode_id' => publicGameMode()->id]);
    $deck->factions()->attach($faction->id);

    // Cartas: 'RG' (2 dados) x3 y 'B' (1 dado) x2 → 5 cartas, media 1.6
    $swords = publicCard(['card_type_id' => $type->id, 'cost' => 'RG']);
    $shield = publicCard(['name' => ['es' => 'Escudo', 'en' => 'Shield'], 'card_type_id' => $type->id, 'cost' => 'B']);
    $draftCard = publicCard(['name' => ['es' => 'Borrador'], 'card_type_id' => $type->id, 'is_published' => false]);
    $deck->cards()->attach([$swords->id => ['copies' => 3], $shield->id => ['copies' => 2], $draftCard->id => ['copies' => 4]]);

    $hero = publicHero(['hero_class_id' => publicHeroClass()->id]);
    $deck->heroes()->attach($hero->id);
    $deck->heroes()->attach(publicHero(['name' => ['es' => 'Borrador'], 'is_published' => false])->id);

    $response = $this->getJson('/api/faction-decks/mazo-inicial')->assertOk();
    $data = $response->json('data');

    expect($data['name'])->toMatchArray(['es' => 'Mazo inicial', 'en' => 'Starter deck'])
        ->and($data['slug'])->toMatchArray(['es' => 'mazo-inicial', 'en' => 'starter-deck'])
        ->and($data['epic_quote'])->toBe('Al combate')
        ->and($data['game_mode']['name'])->toBe('Escaramuza')
        ->and($data['factions'])->toHaveCount(1)
        ->and($data['factions'][0])->toMatchArray(['name' => 'Alianza', 'color' => '#336699']);

    // Cartas publicadas ordenadas por coste (B=001 antes que RG=110) y con copias
    expect($data['cards'])->toHaveCount(2)
        ->and($data['cards'][0])->toMatchArray(['name' => 'Escudo', 'slug' => 'escudo', 'copies' => 2])
        ->and($data['cards'][1])->toMatchArray(['name' => 'Espada corta', 'slug' => 'espada-corta', 'copies' => 3])
        ->and($data['heroes'])->toHaveCount(1)
        ->and($data['heroes'][0])->toMatchArray(['name' => 'Aritz', 'slug' => 'aritz'])
        ->and($data['total_cards'])->toBe(5)
        ->and($data['total_heroes'])->toBe(1);

    // Estadísticas portadas del viejo (solo contenido publicado)
    expect($data['stats'])->toMatchArray([
        'total_cards' => 5,
        'unique_cards' => 2,
        'average_dice' => 1.6,
        'symbols' => ['R' => 3, 'G' => 3, 'B' => 2],
        'total_heroes' => 1,
        'unique_heroes' => 1,
    ])
        ->and($data['stats']['cards_by_dice'])->toBe([
            ['dice' => 1, 'copies' => 2],
            ['dice' => 2, 'copies' => 3],
        ])
        ->and($data['stats']['cards_by_type'])->toBe([['name' => 'Técnica', 'copies' => 5]])
        ->and($data['stats']['heroes_by_class'])->toBe([['name' => 'Guerrero', 'count' => 1]])
        ->and($data['stats']['heroes_by_superclass'])->toBe([['name' => 'Luchador', 'count' => 1]]);
});

it('resuelve el slug en cualquier locale', function () {
    publicDeck();

    $response = $this->getJson('/api/faction-decks/starter-deck')->assertOk();

    expect($response->json('data.slug.es'))->toBe('mazo-inicial');
});

it('devuelve 404 para un mazo sin publicar', function () {
    publicDeck(['is_published' => false]);

    $this->getJson('/api/faction-decks/mazo-inicial')->assertNotFound();
});

it('devuelve 404 para un slug inexistente', function () {
    $this->getJson('/api/faction-decks/no-existe')->assertNotFound();
});
