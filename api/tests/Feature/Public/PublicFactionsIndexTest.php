<?php

// GET /api/factions — índice público de facciones.

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    ensurePublicApiRoutes();
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

it('lista solo facciones publicadas con contadores de contenido publicado', function () {
    $faction = publicFaction();
    publicHero(['faction_id' => $faction->id]);
    publicHero(['name' => ['es' => 'Borrador'], 'faction_id' => $faction->id, 'is_published' => false]);
    publicCard(['faction_id' => $faction->id]);
    publicCard(['name' => ['es' => 'Borrador'], 'faction_id' => $faction->id, 'is_published' => false]);
    publicFaction(['name' => ['es' => 'Sin publicar'], 'is_published' => false]);

    $response = $this->getJson('/api/factions')->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0'))->toMatchArray([
        'id' => $faction->id,
        'name' => 'Alianza',
        'slug' => 'alianza',
        'color' => '#336699',
        'text_is_dark' => false,
        'icon' => null,
        'heroes_count' => 1,
        'cards_count' => 1,
    ]);
});

it('localiza el índice con ?locale', function () {
    publicFaction();

    $response = $this->getJson('/api/factions?locale=en')->assertOk();

    expect($response->json('data.0.name'))->toBe('Alliance')
        ->and($response->json('data.0.slug'))->toBe('alliance');
});

it('busca multi-campo: casa por el lore aunque el nombre no coincida', function () {
    $volcanica = publicFaction([
        'name' => ['es' => 'Alianza', 'en' => 'Alliance'],
        'lore_text' => ['es' => '<p>Forjada entre volcanes.</p>', 'en' => '<p>Forged among volcanoes.</p>'],
    ]);
    publicFaction(['name' => ['es' => 'Horda', 'en' => 'Horde'], 'color' => '#993333']);
    // Aunque el lore case, las no publicadas siguen fuera
    publicFaction([
        'name' => ['es' => 'Borrador', 'en' => 'Draft'],
        'lore_text' => ['es' => '<p>Más volcanes.</p>', 'en' => '<p>More volcanoes.</p>'],
        'is_published' => false,
    ]);

    $response = $this->getJson('/api/factions?search=volcanes')->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$volcanica->id]);

    // Lo que no está en ningún campo buscable no casa
    expect($this->getJson('/api/factions?search=grimorio')->assertOk()->json('data'))->toBeEmpty();
});

it('no exige autenticación', function () {
    $this->getJson('/api/factions')->assertOk();
});

it('ordena con el contrato de sort, incluido oldest', function () {
    $bravo = publicFaction(['name' => ['es' => 'Bravo', 'en' => 'Bravo']]);
    $alfa = publicFaction(['name' => ['es' => 'Alfa', 'en' => 'Alpha']]);

    // Sin sort: orden histórico, nombre asc del locale
    $default = $this->getJson('/api/factions')->assertOk();
    expect(collect($default->json('data'))->pluck('id')->all())->toBe([$alfa->id, $bravo->id]);

    $oldest = $this->getJson('/api/factions?sort=oldest')->assertOk();
    expect(collect($oldest->json('data'))->pluck('id')->all())->toBe([$bravo->id, $alfa->id]);

    $latest = $this->getJson('/api/factions?sort=latest')->assertOk();
    expect(collect($latest->json('data'))->pluck('id')->all())->toBe([$alfa->id, $bravo->id]);

    $desc = $this->getJson('/api/factions?sort=name_desc')->assertOk();
    expect(collect($desc->json('data'))->pluck('id')->all())->toBe([$bravo->id, $alfa->id]);
});
