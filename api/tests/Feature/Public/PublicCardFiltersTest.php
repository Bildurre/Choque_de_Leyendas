<?php

// GET /api/cards/filters — opciones de los selects de filtro del índice.

use App\Support\GameIcons;
use Edc\Core\Icons\Models\Icon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    ensurePublicApiRoutes();
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
    // La cache estática de iconos sobrevive entre tests del mismo proceso
    GameIcons::flush();
});

it('devuelve facciones publicadas y todos los tipos (con flags) localizados', function () {
    $faction = publicFaction();
    publicFaction(['name' => ['es' => 'Sin publicar', 'en' => 'Unpublished'], 'is_published' => false]);
    $weapon = publicCardType(['name' => ['es' => 'Arma', 'en' => 'Weapon'], 'is_equipment' => true]);
    $technique = publicCardType(['allows_subtypes' => true]); // 'Técnica' / 'Technique'

    $response = $this->getJson('/api/cards/filters')->assertOk();

    // Facciones: solo publicadas; nombres YA localizados (string, no mapa)
    $response->assertJsonCount(1, 'factions');
    expect($response->json('factions.0'))->toBe(['id' => $faction->id, 'name' => 'Alianza'])
        // Tipos: todos (no tienen publicación), ordenados por nombre (es),
        // con los flags que deciden qué filtros aplican en el front
        ->and($response->json('types'))->toBe([
            ['id' => $weapon->id, 'name' => 'Arma', 'allows_subtypes' => false, 'is_equipment' => true],
            ['id' => $technique->id, 'name' => 'Técnica', 'allows_subtypes' => true, 'is_equipment' => false],
        ]);
});

it('devuelve subtipos, tipos y subtipos de equipo, rangos y subtipos de ataque localizados', function () {
    $beast = publicCardSubtype(); // 'Bestia' / 'Beast'
    $aura = publicCardSubtype(['name' => ['es' => 'Aura', 'en' => 'Aura']]);
    $weapon = publicEquipmentType(); // 'Arma' / 'Weapon'
    $sword = publicEquipmentSubtype(['equipment_type_id' => $weapon->id]); // 'Espada' / 'Sword'
    $melee = publicAttackRange(); // 'Cuerpo a cuerpo' / 'Melee'
    $slash = publicAttackSubtype(); // 'Corte' / 'Slash'

    $response = $this->getJson('/api/cards/filters')->assertOk();

    expect(collect($response->json('subtypes'))->pluck('id')->all())
        ->toBe([$aura->id, $beast->id]) // orden por nombre (es)
        ->and($response->json('subtypes.0.name'))->toBe('Aura')
        ->and($response->json('equipment_types'))->toBe([['id' => $weapon->id, 'name' => 'Arma']])
        // Los subtipos llevan su tipo: el front acota el select al elegido
        ->and($response->json('equipment_subtypes'))->toBe([
            ['id' => $sword->id, 'name' => 'Espada', 'equipment_type_id' => $weapon->id],
        ])
        ->and($response->json('attack_ranges'))->toBe([['id' => $melee->id, 'name' => 'Cuerpo a cuerpo']])
        ->and($response->json('attack_subtypes'))->toBe([['id' => $slash->id, 'name' => 'Corte']]);
});

it('devuelve las urls de los dados del gestor de iconos (null si no están subidos)', function () {
    // Sin iconos subidos: las tres claves existen con null
    $empty = $this->getJson('/api/cards/filters')->assertOk();
    expect($empty->json('icons'))->toBe([
        'dice-red' => null,
        'dice-green' => null,
        'dice-blue' => null,
    ]);

    // Con el dado rojo subido al gestor: url real; el resto sigue null
    Storage::fake('public');
    $icon = Icon::create(['name' => 'Dice red']); // slug: dice-red
    $icon->addMedia(UploadedFile::fake()->createWithContent('dice-red.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'))
        ->toMediaCollection('image');
    GameIcons::flush();

    $response = $this->getJson('/api/cards/filters')->assertOk();
    expect($response->json('icons.dice-red'))->toBeString()->toContain('dice-red')
        ->and($response->json('icons.dice-green'))->toBeNull()
        ->and($response->json('icons.dice-blue'))->toBeNull();
});

it('localiza los nombres con ?locale', function () {
    publicFaction();
    publicCardType(['name' => ['es' => 'Arma', 'en' => 'Weapon']]);
    publicCardSubtype();
    publicEquipmentSubtype(); // crea también su tipo ('Arma' / 'Weapon')
    publicAttackRange();
    publicAttackSubtype();

    $response = $this->getJson('/api/cards/filters?locale=en')->assertOk();

    expect($response->json('factions.0.name'))->toBe('Alliance')
        ->and($response->json('types.0.name'))->toBe('Weapon')
        ->and($response->json('subtypes.0.name'))->toBe('Beast')
        ->and($response->json('equipment_types.0.name'))->toBe('Weapon')
        ->and($response->json('equipment_subtypes.0.name'))->toBe('Sword')
        ->and($response->json('attack_ranges.0.name'))->toBe('Melee')
        ->and($response->json('attack_subtypes.0.name'))->toBe('Slash');
});
