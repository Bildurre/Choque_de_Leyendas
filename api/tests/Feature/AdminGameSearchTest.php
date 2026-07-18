<?php

// Búsqueda multi-campo (`search`) de los index de admin: HasFilters del motor
// recorre TODAS las columnas de $searchable (LIKE sobre el json traducible
// completo, en cualquier locale). Los campos wysiwyg se buscan con su HTML
// tal cual. Solo entran el nombre y los campos "de juego" (efecto,
// restricción, descripción, pasivas): el lore y la cita épica quedan FUERA
// en todos los modelos. Cada caso casa SOLO por un campo secundario (no por
// el nombre) y comprueba que lo que no es buscable (incluido el lore) no casa.

require_once __DIR__.'/Public/Helpers.php';

beforeEach(function () {
    // Como el admin real: manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

it('busca cartas por el texto del efecto y la restricción', function () {
    $admin = motorUser('admin');

    $robo = publicCard([
        'name' => ['es' => 'Alfa', 'en' => 'Alpha'],
        'effect' => ['es' => 'Roba una carta al rival.', 'en' => 'Steal a card from the rival.'],
    ]);
    $unica = publicCard([
        'name' => ['es' => 'Beta', 'en' => 'Beta'],
        'effect' => ['es' => 'Cura dos puntos.', 'en' => 'Heals two points.'],
        'restriction' => ['es' => 'Solo héroes de la Horda.', 'en' => 'Horde heroes only.'],
        'lore_text' => ['es' => '<p>Forjada en la fragua ancestral.</p>', 'en' => '<p>Forged in the ancient forge.</p>'],
    ]);

    // 'rival' solo aparece en el efecto de la primera
    $byEffect = $this->actingAs($admin)->getJson('/api/admin/cards?search=rival')->assertOk();
    expect(array_column($byEffect->json('data'), 'id'))->toBe([$robo->id]);

    // 'horda' solo aparece en la restricción de la segunda
    $byRestriction = $this->actingAs($admin)->getJson('/api/admin/cards?search=horda')->assertOk();
    expect(array_column($byRestriction->json('data'), 'id'))->toBe([$unica->id]);

    // Lo que no está en ningún campo buscable no casa
    $none = $this->actingAs($admin)->getJson('/api/admin/cards?search=grimorio')->assertOk();
    expect($none->json('data'))->toBeEmpty();

    // El lore ya no es buscable: 'fragua' solo está en el trasfondo
    $byLore = $this->actingAs($admin)->getJson('/api/admin/cards?search=fragua')->assertOk();
    expect($byLore->json('data'))->toBeEmpty();
});

it('busca héroes por la descripción de la pasiva', function () {
    $admin = motorUser('admin');

    $centinela = publicHero([
        'name' => ['es' => 'Aritz', 'en' => 'Aritz the Bold'],
        'passive_name' => ['es' => 'Vigilia', 'en' => 'Vigil'],
        'passive_description' => ['es' => 'Ignora las emboscadas.', 'en' => 'Ignores ambushes.'],
        'lore_text' => ['es' => '<p>Nacido bajo un eclipse.</p>', 'en' => '<p>Born under an eclipse.</p>'],
    ]);
    publicHero(['name' => ['es' => 'Beltza', 'en' => 'Beltza']]);

    // 'emboscadas' solo aparece en la pasiva del primero
    $response = $this->actingAs($admin)->getJson('/api/admin/heroes?search=emboscadas')->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$centinela->id]);

    $none = $this->actingAs($admin)->getJson('/api/admin/heroes?search=grimorio')->assertOk();
    expect($none->json('data'))->toBeEmpty();

    // El lore ya no es buscable: 'eclipse' solo está en el trasfondo
    $byLore = $this->actingAs($admin)->getJson('/api/admin/heroes?search=eclipse')->assertOk();
    expect($byLore->json('data'))->toBeEmpty();
});

it('busca habilidades por la descripción', function () {
    $admin = motorUser('admin');

    $aturdir = publicAbility([
        'name' => ['es' => 'Golpe certero', 'en' => 'True strike'],
        'description' => ['es' => 'Aturde al objetivo un turno.', 'en' => 'Stuns the target for a turn.'],
    ]);
    publicAbility(['name' => ['es' => 'Aullido', 'en' => 'Howl']]);

    // 'aturde' solo aparece en la descripción de la primera
    $response = $this->actingAs($admin)->getJson('/api/admin/hero-abilities?search=aturde')->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$aturdir->id]);

    $none = $this->actingAs($admin)->getJson('/api/admin/hero-abilities?search=grimorio')->assertOk();
    expect($none->json('data'))->toBeEmpty();
});

it('busca contadores por el texto del efecto', function () {
    $admin = motorUser('admin');

    $quemadura = publicCounter([
        'name' => ['es' => 'Quemadura', 'en' => 'Burn'],
        'effect' => ['es' => 'Descarta un dado rojo.', 'en' => 'Discard a red die.'],
    ]);
    publicCounter(['name' => ['es' => 'Veneno', 'en' => 'Poison']]);

    // 'descarta' solo aparece en el efecto del primero
    $response = $this->actingAs($admin)->getJson('/api/admin/counters?search=descarta')->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$quemadura->id]);

    $none = $this->actingAs($admin)->getJson('/api/admin/counters?search=grimorio')->assertOk();
    expect($none->json('data'))->toBeEmpty();
});

it('busca facciones solo por el nombre (el lore queda fuera)', function () {
    $admin = motorUser('admin');

    $volcanica = publicFaction([
        'name' => ['es' => 'Alianza', 'en' => 'Alliance'],
        'lore_text' => ['es' => '<p>Forjada entre volcanes.</p>', 'en' => '<p>Forged among volcanoes.</p>'],
    ]);
    publicFaction(['name' => ['es' => 'Horda', 'en' => 'Horde']]);

    // El nombre sigue casando (locale activo)
    $byName = $this->actingAs($admin)->getJson('/api/admin/factions?search=alianza')->assertOk();
    expect(array_column($byName->json('data'), 'id'))->toBe([$volcanica->id]);

    // El lore ya no es buscable: 'volcanes' solo está en el trasfondo
    $byLore = $this->actingAs($admin)->getJson('/api/admin/factions?search=volcanes')->assertOk();
    expect($byLore->json('data'))->toBeEmpty();

    $none = $this->actingAs($admin)->getJson('/api/admin/factions?search=grimorio')->assertOk();
    expect($none->json('data'))->toBeEmpty();
});

it('busca mazos por la descripción pero ya no por la cita épica', function () {
    $admin = motorUser('admin');

    $iniciacion = publicDeck([
        'name' => ['es' => 'Mazo inicial', 'en' => 'Starter deck'],
        'description' => ['es' => '<p>Pensado para aprender las reglas.</p>', 'en' => '<p>Meant for learning the rules.</p>'],
    ]);
    publicDeck([
        'name' => ['es' => 'Otro mazo', 'en' => 'Other deck'],
        'epic_quote' => ['es' => 'Que tiemblen las montañas', 'en' => 'Let the mountains tremble'],
    ]);

    // 'aprender' solo aparece en la descripción del primero
    $byDescription = $this->actingAs($admin)->getJson('/api/admin/faction-decks?search=aprender')->assertOk();
    expect(array_column($byDescription->json('data'), 'id'))->toBe([$iniciacion->id]);

    // La cita ya no es buscable: 'tiemblen' solo está en la cita del segundo
    $byQuote = $this->actingAs($admin)->getJson('/api/admin/faction-decks?search=tiemblen')->assertOk();
    expect($byQuote->json('data'))->toBeEmpty();

    $none = $this->actingAs($admin)->getJson('/api/admin/faction-decks?search=grimorio')->assertOk();
    expect($none->json('data'))->toBeEmpty();
});
