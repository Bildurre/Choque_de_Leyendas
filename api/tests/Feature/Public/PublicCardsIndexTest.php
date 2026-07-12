<?php

// GET /api/cards — índice público de cartas con filtros de juego.

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    ensurePublicApiRoutes();
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

it('lista solo cartas publicadas con la forma del catálogo y orden id desc', function () {
    $first = publicCard(['name' => ['es' => 'Alfa', 'en' => 'Alpha']]);
    $second = publicCard(['name' => ['es' => 'Beta', 'en' => 'Beta']]);
    publicCard(['name' => ['es' => 'Borrador', 'en' => 'Draft'], 'is_published' => false]);

    $response = $this->getJson('/api/cards')->assertOk();

    $response->assertJsonCount(2, 'data');
    // Misma forma que /api/catalog/card del motor: {id, name, slug, preview}
    expect($response->json('data.0'))->toBe([
        'id' => $second->id,
        'name' => 'Beta',
        'slug' => 'beta',
        'preview' => null,
    ])
        ->and($response->json('data.1.id'))->toBe($first->id)
        ->and($response->json('meta'))->toMatchArray([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 24,
            'total' => 2,
        ]);
});

it('filtra por facción', function () {
    $faction = publicFaction();
    $mine = publicCard(['name' => ['es' => 'De la Alianza', 'en' => 'Alliance one'], 'faction_id' => $faction->id]);
    publicCard(['name' => ['es' => 'Neutral', 'en' => 'Neutral']]);

    $response = $this->getJson("/api/cards?faction_id={$faction->id}")->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($mine->id);
});

it('filtra por tipo de carta', function () {
    $type = publicCardType(['name' => ['es' => 'Conjuro', 'en' => 'Spell']]);
    $mine = publicCard(['name' => ['es' => 'Bola de fuego', 'en' => 'Fireball'], 'card_type_id' => $type->id]);
    publicCard(['name' => ['es' => 'Otra', 'en' => 'Other']]);

    $response = $this->getJson("/api/cards?card_type_id={$type->id}")->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($mine->id);
});

it('filtra por cost_total (nº exacto de dados)', function () {
    publicCard(['name' => ['es' => 'Un dado', 'en' => 'One die'], 'cost' => 'R']);
    $two = publicCard(['name' => ['es' => 'Dos dados', 'en' => 'Two dice'], 'cost' => 'RG']);
    publicCard(['name' => ['es' => 'Tres dados', 'en' => 'Three dice'], 'cost' => 'RRG']);

    $response = $this->getJson('/api/cards?cost_total=2')->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($two->id);
});

it('filtra por cost_colors: al menos un dado de cada color pedido', function () {
    $rg = publicCard(['name' => ['es' => 'Roja y verde', 'en' => 'Red green'], 'cost' => 'RG']);
    publicCard(['name' => ['es' => 'Roja y azul', 'en' => 'Red blue'], 'cost' => 'RRB']);
    publicCard(['name' => ['es' => 'Verde y azul', 'en' => 'Green blue'], 'cost' => 'GB']);
    $rgb = publicCard(['name' => ['es' => 'Tricolor', 'en' => 'Tricolor'], 'cost' => 'RGB']);

    // Contiene al menos R y G (los dados extra no molestan)
    $response = $this->getJson('/api/cards?cost_colors=RG')->assertOk();

    $response->assertJsonCount(2, 'data');
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$rgb->id, $rg->id]); // orden id desc
});

it('normaliza cost_colors: minúsculas y orden libre valen igual', function () {
    $rg = publicCard(['name' => ['es' => 'Roja y verde', 'en' => 'Red green'], 'cost' => 'RG']);
    publicCard(['name' => ['es' => 'Solo roja', 'en' => 'Red only'], 'cost' => 'RR']);

    // "gr" === "RG": se pasa a mayúsculas y se reordena a canónico R→G→B
    $response = $this->getJson('/api/cards?cost_colors=gr')->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($rg->id);
});

it('combina filtros (facción + cost_colors + cost_total)', function () {
    $faction = publicFaction();
    $match = publicCard(['name' => ['es' => 'La buena', 'en' => 'The one'], 'faction_id' => $faction->id, 'cost' => 'RGB']);
    publicCard(['name' => ['es' => 'Sin facción', 'en' => 'Factionless'], 'cost' => 'RGB']);
    publicCard(['name' => ['es' => 'Coste corto', 'en' => 'Short cost'], 'faction_id' => $faction->id, 'cost' => 'RG']);

    $response = $this->getJson("/api/cards?faction_id={$faction->id}&cost_colors=RG&cost_total=3")->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($match->id);
});

it('busca por nombre en el locale activo', function () {
    publicCard(['name' => ['es' => 'Espada corta', 'en' => 'Short sword']]);
    publicCard(['name' => ['es' => 'Escudo', 'en' => 'Shield']]);

    $response = $this->getJson('/api/cards?search=espada')->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.name'))->toBe('Espada corta');
});

it('pagina con per_page y lo limita a 48', function () {
    publicCard(['name' => ['es' => 'Una', 'en' => 'One']]);
    publicCard(['name' => ['es' => 'Otra', 'en' => 'Two']]);

    $paged = $this->getJson('/api/cards?per_page=1')->assertOk();
    expect($paged->json('meta'))->toMatchArray(['per_page' => 1, 'last_page' => 2, 'total' => 2]);

    $capped = $this->getJson('/api/cards?per_page=100')->assertOk();
    expect($capped->json('meta.per_page'))->toBe(48);
});
