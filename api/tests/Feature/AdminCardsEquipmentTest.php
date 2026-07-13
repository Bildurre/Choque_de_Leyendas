<?php

// Facción obligatoria en cartas + tipado de equipo (tipo → subtipo → manos).

use App\Models\Card;

require_once __DIR__.'/Public/Helpers.php';

beforeEach(function () {
    $this->withHeader('Accept-Language', 'es');
});

/** Payload mínimo válido de carta (se sobreescribe por test). */
function cardPayload(array $overrides = []): array
{
    return array_merge([
        'name' => ['es' => 'Carta de prueba'],
        'faction_id' => publicFaction(['is_published' => false])->id,
        'card_type_id' => publicCardType()->id,
        'area' => '0',
        'is_unique' => '0',
        'is_published' => '0',
    ], $overrides);
}

it('exige facción al crear y editar una carta', function () {
    $admin = motorUser('admin');

    $payload = cardPayload();
    unset($payload['faction_id']);

    $this->actingAs($admin)
        ->postJson('/api/admin/cards', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['faction_id']);

    // Con facción se crea con normalidad
    $response = $this->actingAs($admin)
        ->postJson('/api/admin/cards', cardPayload())
        ->assertCreated();
    expect($response->json('data.faction_id'))->not->toBeNull();
});

it('exige tipo y subtipo de equipo (y que el subtipo pertenezca al tipo) en cartas de equipo', function () {
    $admin = motorUser('admin');

    $equipmentCardType = publicCardType(['name' => ['es' => 'Equipo'], 'is_equipment' => true]);
    $weapon = publicEquipmentType(); // 'Arma', uses_hands
    $armor = publicEquipmentType(['name' => ['es' => 'Armadura'], 'uses_hands' => false]);
    $sword = publicEquipmentSubtype(['equipment_type_id' => $weapon->id]);
    $helmet = publicEquipmentSubtype(['name' => ['es' => 'Yelmo'], 'equipment_type_id' => $armor->id]);

    // Sin tipo ni subtipo de equipo: ambos obligatorios para equipo
    $this->actingAs($admin)
        ->postJson('/api/admin/cards', cardPayload(['card_type_id' => $equipmentCardType->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['equipment_type_id', 'equipment_subtype_id']);

    // Subtipo de otro tipo: rechazado
    $this->actingAs($admin)
        ->postJson('/api/admin/cards', cardPayload([
            'card_type_id' => $equipmentCardType->id,
            'equipment_type_id' => $weapon->id,
            'equipment_subtype_id' => $helmet->id,
            'hands' => 1,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['equipment_subtype_id']);

    // Arma (uses_hands) sin manos: rechazada
    $this->actingAs($admin)
        ->postJson('/api/admin/cards', cardPayload([
            'card_type_id' => $equipmentCardType->id,
            'equipment_type_id' => $weapon->id,
            'equipment_subtype_id' => $sword->id,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['hands']);

    // Arma completa: creada con su tipado entero
    $response = $this->actingAs($admin)
        ->postJson('/api/admin/cards', cardPayload([
            'card_type_id' => $equipmentCardType->id,
            'equipment_type_id' => $weapon->id,
            'equipment_subtype_id' => $sword->id,
            'hands' => 2,
        ]))
        ->assertCreated();
    expect($response->json('data.equipment_type.uses_hands'))->toBeTrue()
        ->and($response->json('data.equipment_subtype.id'))->toBe($sword->id)
        ->and($response->json('data.hands'))->toBe(2);

    // Armadura (sin manos): se crea y las manos que lleguen se anulan
    $response = $this->actingAs($admin)
        ->postJson('/api/admin/cards', cardPayload([
            'card_type_id' => $equipmentCardType->id,
            'equipment_type_id' => $armor->id,
            'equipment_subtype_id' => $helmet->id,
            'hands' => 1,
        ]))
        ->assertCreated();
    expect($response->json('data.hands'))->toBeNull();
});

it('CRUD de subtipos de equipo: tipo obligatorio, options e index filtrable', function () {
    $admin = motorUser('admin');

    $weapon = publicEquipmentType();
    $armor = publicEquipmentType(['name' => ['es' => 'Armadura'], 'uses_hands' => false]);

    // El subtipo exige tipo de equipo
    $this->actingAs($admin)
        ->postJson('/api/admin/equipment-subtypes', ['name' => ['es' => 'Espada']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['equipment_type_id']);

    $sword = $this->actingAs($admin)
        ->postJson('/api/admin/equipment-subtypes', [
            'name' => ['es' => 'Espada'],
            'equipment_type_id' => $weapon->id,
        ])
        ->assertCreated();
    expect($sword->json('data.equipment_type.id'))->toBe($weapon->id);

    $helmet = publicEquipmentSubtype(['name' => ['es' => 'Yelmo'], 'equipment_type_id' => $armor->id]);

    // Options: con el tipo al que pertenece (para acotar selects)
    $options = $this->actingAs($admin)
        ->getJson('/api/admin/equipment-subtypes/options')
        ->assertOk();
    expect(array_column($options->json('data'), 'equipment_type_id'))
        ->toContain($weapon->id, $armor->id);

    // Index filtrable por tipo de equipo
    $response = $this->actingAs($admin)
        ->getJson("/api/admin/equipment-subtypes?equipment_type_id={$armor->id}")
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$helmet->id]);
});

it('renderData incluye el tipado de equipo completo y la habilidad de héroe', function () {
    $range = publicAttackRange();
    $slash = publicAttackSubtype();
    $ability = publicAbility([
        'attack_type' => 'physical',
        'attack_range_id' => $range->id,
        'attack_subtype_id' => $slash->id,
        'area' => true,
    ]);
    $weapon = publicEquipmentType();
    $sword = publicEquipmentSubtype(['equipment_type_id' => $weapon->id]);
    $card = publicCard([
        'card_type_id' => publicCardType(['name' => ['es' => 'Equipo'], 'is_equipment' => true])->id,
        'equipment_type_id' => $weapon->id,
        'equipment_subtype_id' => $sword->id,
        'hands' => 2,
        'hero_ability_id' => $ability->id,
    ]);

    $data = Card::findOrFail($card->id)->renderData('es');

    expect($data['equipment_type'])->toBe('Arma')
        ->and($data['equipment_subtype'])->toBe('Espada')
        ->and($data['hands'])->toBe(2)
        ->and($data['hero_ability']['name'])->toBe('Golpe certero')
        ->and($data['hero_ability']['attack'])->toBe([
            'type' => 'physical',
            'range' => 'Cuerpo a cuerpo',
            'subtype' => 'Corte',
        ])
        ->and($data['hero_ability']['area'])->toBeTrue()
        ->and($data['hero_ability']['cost_parsed'])->toBe([
            ['color' => 'red', 'letter' => 'R'],
            ['color' => 'blue', 'letter' => 'B'],
        ])
        ->and($data['hero_ability']['description'])->toBe('Hace daño.');
});
