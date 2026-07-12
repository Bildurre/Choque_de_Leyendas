<?php

// Contrato de ordenación (`sort`) y filtros nuevos de los index de admin.

use App\Models\HeroAbility;

require_once __DIR__.'/Public/Helpers.php';

beforeEach(function () {
    // Como el admin real: manda Accept-Language (es); ordena en ese locale
    $this->withHeader('Accept-Language', 'es');
});

/** Tres habilidades con nombres B/A/C: cada orden posible es distinguible. */
function threeAbilities(): array
{
    return [
        publicAbility(['name' => ['es' => 'Bola', 'en' => 'Bolt']]),
        publicAbility(['name' => ['es' => 'Aullido', 'en' => 'Howl']]),
        publicAbility(['name' => ['es' => 'Zarpazo', 'en' => 'Claw']]),
    ];
}

it('ordena un index de admin por nombre asc y desc con sort', function () {
    $admin = motorUser('admin');
    [$bola, $aullido, $zarpazo] = threeAbilities();

    $asc = $this->actingAs($admin)
        ->getJson('/api/admin/hero-abilities?sort=name')
        ->assertOk();
    expect(array_column($asc->json('data'), 'id'))
        ->toBe([$aullido->id, $bola->id, $zarpazo->id]);

    $desc = $this->actingAs($admin)
        ->getJson('/api/admin/hero-abilities?sort=name_desc')
        ->assertOk();
    expect(array_column($desc->json('data'), 'id'))
        ->toBe([$zarpazo->id, $bola->id, $aullido->id]);
});

it('sin sort (o con un valor desconocido) mantiene el orden id desc', function () {
    $admin = motorUser('admin');
    [$bola, $aullido, $zarpazo] = threeAbilities();

    foreach (['/api/admin/hero-abilities', '/api/admin/hero-abilities?sort=latest', '/api/admin/hero-abilities?sort=raro'] as $url) {
        $response = $this->actingAs($admin)->getJson($url)->assertOk();
        expect(array_column($response->json('data'), 'id'))
            ->toBe([$zarpazo->id, $aullido->id, $bola->id]);
    }
});

it('filtra las habilidades por attack_type', function () {
    $admin = motorUser('admin');

    $fisica = publicAbility(['name' => ['es' => 'Zarpazo', 'en' => 'Claw']]);
    $fisica->attack_type = 'physical';
    $fisica->save();

    $magica = publicAbility(['name' => ['es' => 'Bola de fuego', 'en' => 'Fireball']]);
    $magica->attack_type = 'magical';
    $magica->save();

    $neutra = publicAbility(['name' => ['es' => 'Aullido', 'en' => 'Howl']]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/hero-abilities?attack_type=physical')
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$fisica->id]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/hero-abilities?attack_type=magical')
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$magica->id]);

    // Valores desconocidos se ignoran (no filtran)
    $response = $this->actingAs($admin)
        ->getJson('/api/admin/hero-abilities?attack_type=raro')
        ->assertOk();
    expect($response->json('data'))->toHaveCount(3)
        ->and(HeroAbility::count())->toBe(3)
        ->and($neutra->attack_type)->toBeNull();
});

it('filtra las cartas de admin por faction_id y card_type_id', function () {
    $admin = motorUser('admin');

    $faction = publicFaction();
    $type = publicCardType(['name' => ['es' => 'Conjuro', 'en' => 'Spell']]);

    $deFaccion = publicCard(['name' => ['es' => 'De la Alianza', 'en' => 'Alliance one'], 'faction_id' => $faction->id]);
    $conjuro = publicCard(['name' => ['es' => 'Bola de fuego', 'en' => 'Fireball'], 'card_type_id' => $type->id]);
    publicCard(['name' => ['es' => 'Neutral', 'en' => 'Neutral']]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/cards?faction_id={$faction->id}")
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$deFaccion->id]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/cards?card_type_id={$type->id}")
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$conjuro->id]);

    // Combinados: ninguna carta cumple ambos a la vez
    $response = $this->actingAs($admin)
        ->getJson("/api/admin/cards?faction_id={$faction->id}&card_type_id={$type->id}")
        ->assertOk();
    expect($response->json('data'))->toBeEmpty();
});
