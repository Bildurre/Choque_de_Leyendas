<?php

// GET /api/admin/hero-abilities/options — selector enriquecido de habilidades.

use App\Models\AttackRange;
use App\Models\AttackSubtype;

require_once __DIR__.'/Public/Helpers.php';

beforeEach(function () {
    // Como el admin real: manda Accept-Language (es); ordena en ese locale
    $this->withHeader('Accept-Language', 'es');
});

it('devuelve las habilidades con datos de ataque, ordenadas por nombre', function () {
    $admin = motorUser('admin');

    $range = new AttackRange;
    $range->setTranslations('name', ['es' => 'Melé', 'en' => 'Melee']);
    $range->save();

    $subtype = new AttackSubtype;
    $subtype->setTranslations('name', ['es' => 'Corte', 'en' => 'Slash']);
    $subtype->save();

    $zarpazo = publicAbility(['name' => ['es' => 'Zarpazo', 'en' => 'Claw']]);
    $zarpazo->attack_type = 'physical';
    $zarpazo->attack_range_id = $range->id;
    $zarpazo->attack_subtype_id = $subtype->id;
    $zarpazo->save();

    $aullido = publicAbility(['name' => ['es' => 'Aullido', 'en' => 'Howl'], 'cost' => 'GG']);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/hero-abilities/options')
        ->assertOk();

    $data = $response->json('data');

    // Orden por nombre del locale (es): Aullido < Zarpazo (antes era id desc)
    expect(array_column($data, 'id'))->toBe([$aullido->id, $zarpazo->id])
        // Compatibilidad: name sigue siendo el mapa completo y cost el string
        ->and($data[1])->toBe([
            'id' => $zarpazo->id,
            'name' => ['es' => 'Zarpazo', 'en' => 'Claw'],
            'cost' => 'RB',
            'attack_type' => 'physical',
            'range' => ['id' => $range->id, 'name' => ['es' => 'Melé', 'en' => 'Melee']],
            'subtype' => ['id' => $subtype->id, 'name' => ['es' => 'Corte', 'en' => 'Slash']],
        ])
        ->and($data[0])->toMatchArray([
            'id' => $aullido->id,
            'cost' => 'GG',
            'attack_type' => null,
            'range' => null,
            'subtype' => null,
        ]);
});
