<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('sirve el icono sobre el host de la petición aunque APP_URL apunte a otro', function () {
    // El disco 'public' construye sus URLs con APP_URL, que puede no ser el
    // host real de la petición (p. ej. :8010 vs :8020): la API debe
    // reconstruirlas sobre la petición (PublicUrl::onRequestHost, motor).
    Storage::fake('public');

    $faction = publicFaction();
    $faction->addMedia(UploadedFile::fake()->image('emblema.png'))
        ->toMediaCollection('image');

    // Petición con host explícito, distinto del APP_URL del disco
    $response = $this->getJson('http://host-real.test/api/factions')->assertOk();

    expect($response->json('data.0.icon'))->toContain('emblema')
        ->and($response->json('data.0.icon'))->toStartWith('http://host-real.test/');
});

it('localiza el índice con ?locale', function () {
    publicFaction();

    $response = $this->getJson('/api/factions?locale=en')->assertOk();

    expect($response->json('data.0.name'))->toBe('Alliance')
        ->and($response->json('data.0.slug'))->toBe('alliance');
});

it('busca por el nombre en cualquier locale; el lore ya no es buscable', function () {
    $volcanica = publicFaction([
        'name' => ['es' => 'Alianza', 'en' => 'Alliance'],
        'lore_text' => ['es' => '<p>Forjada entre volcanes.</p>', 'en' => '<p>Forged among volcanoes.</p>'],
    ]);
    publicFaction(['name' => ['es' => 'Horda', 'en' => 'Horde'], 'color' => '#993333']);
    // Aunque el nombre case, las no publicadas siguen fuera
    publicFaction([
        'name' => ['es' => 'Alianza rota', 'en' => 'Broken Alliance'],
        'is_published' => false,
    ]);

    // Casa por el nombre (locale activo); las no publicadas siguen fuera
    $response = $this->getJson('/api/factions?search=alianza')->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$volcanica->id]);

    // El lore quedó fuera de la búsqueda: 'volcanes' solo está en el lore
    expect($this->getJson('/api/factions?search=volcanes')->assertOk()->json('data'))->toBeEmpty();

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
