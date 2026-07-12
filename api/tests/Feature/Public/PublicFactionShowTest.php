<?php

// GET /api/factions/{slug} — ficha pública de facción.

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    ensurePublicApiRoutes();
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

it('muestra la facción publicada con sus listas de contenido publicado', function () {
    $faction = publicFaction();
    $hero = publicHero(['faction_id' => $faction->id]);
    publicHero(['name' => ['es' => 'Héroe borrador'], 'faction_id' => $faction->id, 'is_published' => false]);
    $card = publicCard(['faction_id' => $faction->id]);
    publicCard(['name' => ['es' => 'Carta borrador'], 'faction_id' => $faction->id, 'is_published' => false]);

    $deck = publicDeck(['game_mode_id' => publicGameMode()->id]);
    $deck->factions()->attach($faction->id);
    $deck->cards()->attach($card->id, ['copies' => 3]);
    $deck->heroes()->attach($hero->id);
    $draftDeck = publicDeck(['name' => ['es' => 'Mazo borrador'], 'is_published' => false]);
    $draftDeck->factions()->attach($faction->id);

    $response = $this->getJson('/api/factions/alianza')->assertOk();
    $data = $response->json('data');

    // name/slug por locales (canónica + hreflang de EntityDetailView)
    expect($data['name'])->toMatchArray(['es' => 'Alianza', 'en' => 'Alliance'])
        ->and($data['slug'])->toMatchArray(['es' => 'alianza', 'en' => 'alliance'])
        ->and($data['lore_text'])->toBe('<p>Trasfondo</p>')
        ->and($data['epic_quote'])->toBe('Cita épica')
        ->and($data['heroes_count'])->toBe(1)
        ->and($data['cards_count'])->toBe(1)
        ->and($data['decks_count'])->toBe(1);

    // Solo lo publicado, en formato ítem de catálogo {id, name, slug, preview}
    expect($data['heroes'])->toHaveCount(1)
        ->and($data['heroes'][0])->toMatchArray(['id' => $hero->id, 'name' => 'Aritz', 'slug' => 'aritz', 'preview' => null])
        ->and($data['cards'])->toHaveCount(1)
        ->and($data['cards'][0]['slug'])->toBe('espada-corta')
        ->and($data['decks'])->toHaveCount(1)
        ->and($data['decks'][0])->toMatchArray(['name' => 'Mazo inicial', 'total_cards' => 3, 'total_heroes' => 1])
        ->and($data['decks'][0]['game_mode']['name'])->toBe('Escaramuza');
});

it('resuelve el slug en cualquier locale', function () {
    publicFaction();

    $response = $this->getJson('/api/factions/alliance')->assertOk();

    expect($response->json('data.slug.es'))->toBe('alianza');
});

it('devuelve 404 para una facción sin publicar', function () {
    publicFaction(['is_published' => false]);

    $this->getJson('/api/factions/alianza')->assertNotFound();
});

it('devuelve 404 para un slug inexistente', function () {
    $this->getJson('/api/factions/no-existe')->assertNotFound();
});
