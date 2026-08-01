<?php

// GET /api/cards/{slug} — ficha pública de carta.

use App\Models\HeroSuperclass;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    ensurePublicApiRoutes();
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

it('muestra la carta publicada con coste parseado y campos según flags del tipo', function () {
    $faction = publicFaction();
    $type = publicCardType(['name' => ['es' => 'Equipo', 'en' => 'Equipment'], 'is_equipment' => true]);
    // Superclase asociada al tipo: el single enlaza su value al índice de
    // héroes filtrado por ella (necesita el id junto al nombre)
    $superclass = new HeroSuperclass;
    $superclass->setTranslations('name', ['es' => 'Luchador', 'en' => 'Fighter']);
    $superclass->save();
    $type->hero_superclass_id = $superclass->id;
    $type->save();
    $ability = publicAbility();
    $weapon = publicEquipmentType(); // 'Arma' / 'Weapon'
    $sword = publicEquipmentSubtype(['equipment_type_id' => $weapon->id]); // 'Espada' / 'Sword'
    $card = publicCard([
        'faction_id' => $faction->id,
        'card_type_id' => $type->id,
        'hero_ability_id' => $ability->id,
        'equipment_type_id' => $weapon->id,
        'equipment_subtype_id' => $sword->id,
        'hands' => 2,
        'cost' => 'RRG',
        'is_unique' => true,
    ]);
    $card->setTranslations('restriction', ['es' => 'Solo guerreros.', 'en' => 'Warriors only.']);
    $card->save();

    $response = $this->getJson('/api/cards/espada-corta')->assertOk();
    $data = $response->json('data');

    expect($data['name'])->toMatchArray(['es' => 'Espada corta', 'en' => 'Short sword'])
        ->and($data['slug'])->toMatchArray(['es' => 'espada-corta', 'en' => 'short-sword'])
        ->and($data['faction'])->toMatchArray(['name' => 'Alianza', 'slug' => 'alianza', 'color' => '#336699'])
        // Con los ids del tipo Y de su superclase: el single enlaza el tipo
        // al índice de cartas filtrado y la superclase al de héroes
        ->and($data['type'])->toMatchArray(['id' => $type->id, 'name' => 'Equipo', 'superclass' => 'Luchador', 'superclass_id' => $superclass->id, 'allows_subtypes' => false, 'is_equipment' => true])
        // El tipo no admite subtipos → subtype null; es equipo → hands presentes
        ->and($data['subtype'])->toBeNull()
        ->and($data['subtype_id'])->toBeNull()
        // Tipado completo del equipo: tipo, subtipo y manos, con sus ids
        ->and($data['equipment'])->toBe([
            'type' => 'Arma',
            'type_id' => $weapon->id,
            'subtype' => 'Espada',
            'subtype_id' => $sword->id,
            'hands' => 2,
        ])
        ->and($data['cost'])->toBe('RRG')
        ->and($data['cost_parsed'])->toBe([
            ['color' => 'red', 'letter' => 'R'],
            ['color' => 'red', 'letter' => 'R'],
            ['color' => 'green', 'letter' => 'G'],
        ])
        ->and($data['is_unique'])->toBeTrue()
        ->and($data['effect'])->toBe('Golpea dos veces.')
        ->and($data['restriction'])->toBe('Solo guerreros.')
        ->and($data['granted_ability']['name'])->toBe('Golpe certero')
        ->and($data['granted_ability']['cost_parsed'])->toBe([
            ['color' => 'red', 'letter' => 'R'],
            ['color' => 'blue', 'letter' => 'B'],
        ])
        ->and($data['preview'])->toBeNull()
        ->and($data['lore_text'])->toBe('<p>Forjada en EdC.</p>');
});

it('expone los ids del ataque para los enlaces filtrados del single', function () {
    $melee = publicAttackRange(); // 'Cuerpo a cuerpo' / 'Melee'
    publicCard([
        'attack_type' => 'physical',
        'attack_range_id' => $melee->id,
    ]);

    $data = $this->getJson('/api/cards/espada-corta')->assertOk()->json('data');

    expect($data['attack']['type'])->toBe('physical')
        ->and($data['attack']['range'])->toBe('Cuerpo a cuerpo')
        ->and($data['attack']['range_id'])->toBe($melee->id)
        ->and($data['attack']['subtype_id'])->toBeNull();
});

it('localiza la ficha con ?locale', function () {
    publicCard();

    $response = $this->getJson('/api/cards/espada-corta?locale=en')->assertOk();

    expect($response->json('data.effect'))->toBe('Strikes twice.');
});

it('resuelve el slug en cualquier locale', function () {
    publicCard();

    $response = $this->getJson('/api/cards/short-sword')->assertOk();

    expect($response->json('data.slug.es'))->toBe('espada-corta');
});

it('devuelve 404 para una carta sin publicar', function () {
    publicCard(['is_published' => false]);

    $this->getJson('/api/cards/espada-corta')->assertNotFound();
});

it('devuelve 404 para un slug inexistente', function () {
    $this->getJson('/api/cards/no-existe')->assertNotFound();
});
