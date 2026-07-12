<?php

// GET /api/heroes — índice público de héroes con filtros de juego.

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    ensurePublicApiRoutes();
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

it('lista solo héroes publicados con la forma del catálogo y orden id desc', function () {
    $first = publicHero(['name' => ['es' => 'Aritz', 'en' => 'Aritz the Bold']]);
    $second = publicHero(['name' => ['es' => 'Beltza', 'en' => 'Beltza']]);
    publicHero(['name' => ['es' => 'Borrador', 'en' => 'Draft'], 'is_published' => false]);

    $response = $this->getJson('/api/heroes')->assertOk();

    $response->assertJsonCount(2, 'data');
    // Misma forma que /api/catalog/hero del motor: {id, name, slug, preview}
    expect($response->json('data.0'))->toBe([
        'id' => $second->id,
        'name' => 'Beltza',
        'slug' => 'beltza',
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

it('busca por nombre en el locale activo', function () {
    publicHero(['name' => ['es' => 'Aritz', 'en' => 'Aritz the Bold']]);
    publicHero(['name' => ['es' => 'Beltza', 'en' => 'Beltza']]);

    $response = $this->getJson('/api/heroes?search=aritz')->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.name'))->toBe('Aritz');
});

it('filtra por facción, clase y raza', function () {
    $faction = publicFaction();
    $class = publicHeroClass();
    $race = publicHeroRace();

    $deFaccion = publicHero(['name' => ['es' => 'Aliado', 'en' => 'Ally'], 'faction_id' => $faction->id]);
    $guerrero = publicHero(['name' => ['es' => 'Guerrero', 'en' => 'Warrior'], 'hero_class_id' => $class->id]);
    $humano = publicHero(['name' => ['es' => 'Humano', 'en' => 'Human'], 'hero_race_id' => $race->id]);
    publicHero(['name' => ['es' => 'Neutro', 'en' => 'Plain']]);

    $byFaction = $this->getJson("/api/heroes?faction_id={$faction->id}")->assertOk();
    expect(collect($byFaction->json('data'))->pluck('id')->all())->toBe([$deFaccion->id]);

    $byClass = $this->getJson("/api/heroes?hero_class_id={$class->id}")->assertOk();
    expect(collect($byClass->json('data'))->pluck('id')->all())->toBe([$guerrero->id]);

    $byRace = $this->getJson("/api/heroes?hero_race_id={$race->id}")->assertOk();
    expect(collect($byRace->json('data'))->pluck('id')->all())->toBe([$humano->id]);
});

it('filtra por superclase a través de la clase del héroe', function () {
    $warriorClass = publicHeroClass(); // superclase propia: 'Luchador'
    $mageClass = publicHeroClass([
        'name' => ['es' => 'Mago', 'en' => 'Mage'],
        'superclass_name' => ['es' => 'Conjurador', 'en' => 'Spellcaster'],
    ]);

    $luchador = publicHero(['name' => ['es' => 'Bruto', 'en' => 'Brute'], 'hero_class_id' => $warriorClass->id]);
    publicHero(['name' => ['es' => 'Hechicero', 'en' => 'Sorcerer'], 'hero_class_id' => $mageClass->id]);
    publicHero(['name' => ['es' => 'Sin clase', 'en' => 'Classless']]);

    $response = $this->getJson("/api/heroes?hero_superclass_id={$warriorClass->hero_superclass_id}")->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$luchador->id]);
});

it('pagina con per_page y lo limita a 48', function () {
    publicHero(['name' => ['es' => 'Uno', 'en' => 'One']]);
    publicHero(['name' => ['es' => 'Dos', 'en' => 'Two']]);

    $paged = $this->getJson('/api/heroes?per_page=1')->assertOk();
    expect($paged->json('meta'))->toMatchArray(['per_page' => 1, 'last_page' => 2, 'total' => 2]);

    $capped = $this->getJson('/api/heroes?per_page=100')->assertOk();
    expect($capped->json('meta.per_page'))->toBe(48);
});

it('ordena con el contrato completo de sort (name|name_desc|latest|oldest)', function () {
    $bruma = publicHero(['name' => ['es' => 'Bruma', 'en' => 'Mist']]);
    $alfa = publicHero(['name' => ['es' => 'Alfa', 'en' => 'Alpha']]);
    $cieno = publicHero(['name' => ['es' => 'Cieno', 'en' => 'Silt']]);

    $asc = $this->getJson('/api/heroes?sort=name')->assertOk();
    expect(collect($asc->json('data'))->pluck('id')->all())
        ->toBe([$alfa->id, $bruma->id, $cieno->id]);

    $desc = $this->getJson('/api/heroes?sort=name_desc')->assertOk();
    expect(collect($desc->json('data'))->pluck('id')->all())
        ->toBe([$cieno->id, $bruma->id, $alfa->id]);

    $oldest = $this->getJson('/api/heroes?sort=oldest')->assertOk();
    expect(collect($oldest->json('data'))->pluck('id')->all())
        ->toBe([$bruma->id, $alfa->id, $cieno->id]);

    // `latest` o un valor desconocido caen al orden por defecto: id desc
    foreach (['latest', 'raro'] as $sort) {
        $fallback = $this->getJson("/api/heroes?sort={$sort}")->assertOk();
        expect(collect($fallback->json('data'))->pluck('id')->all())
            ->toBe([$cieno->id, $alfa->id, $bruma->id]);
    }
});

it('localiza el índice con ?locale', function () {
    publicHero();

    $response = $this->getJson('/api/heroes?locale=en')->assertOk();

    expect($response->json('data.0.name'))->toBe('Aritz the Bold')
        ->and($response->json('data.0.slug'))->toBe('aritz-the-bold');
});

it('no exige autenticación', function () {
    $this->getJson('/api/heroes')->assertOk();
});
