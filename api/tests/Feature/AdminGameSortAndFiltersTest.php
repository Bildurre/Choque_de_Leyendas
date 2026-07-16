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

it('filtra los héroes de admin por faction_id, hero_superclass_id, hero_class_id y hero_race_id', function () {
    $admin = motorUser('admin');

    $faction = publicFaction();
    $race = publicHeroRace(['name' => ['es' => 'Elfo', 'en' => 'Elf']]);
    $warriorClass = publicHeroClass([
        'name' => ['es' => 'Guerrero', 'en' => 'Warrior'],
        'superclass_name' => ['es' => 'Luchador', 'en' => 'Fighter'],
    ]);

    $deFaccion = publicHero(['name' => ['es' => 'De la Alianza', 'en' => 'Alliance one'], 'faction_id' => $faction->id]);
    $deRaza = publicHero(['name' => ['es' => 'Elfo veloz', 'en' => 'Swift elf'], 'hero_race_id' => $race->id]);
    $deClase = publicHero(['name' => ['es' => 'Guerrero bravo', 'en' => 'Brave warrior'], 'hero_class_id' => $warriorClass->id]);
    publicHero(['name' => ['es' => 'Neutral', 'en' => 'Neutral']]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/heroes?faction_id={$faction->id}")
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$deFaccion->id]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/heroes?hero_race_id={$race->id}")
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$deRaza->id]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/heroes?hero_class_id={$warriorClass->id}")
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$deClase->id]);

    // La superclase llega a través de la clase (el héroe no la guarda).
    $response = $this->actingAs($admin)
        ->getJson("/api/admin/heroes?hero_superclass_id={$warriorClass->hero_superclass_id}")
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$deClase->id]);

    // Combinados: ningún héroe cumple facción y clase a la vez
    $response = $this->actingAs($admin)
        ->getJson("/api/admin/heroes?faction_id={$faction->id}&hero_class_id={$warriorClass->id}")
        ->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('ordena un index de admin por id asc con sort=oldest', function () {
    $admin = motorUser('admin');
    [$bola, $aullido, $zarpazo] = threeAbilities();

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/hero-abilities?sort=oldest')
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))
        ->toBe([$bola->id, $aullido->id, $zarpazo->id]);
});

it('filtra las habilidades por attack_range_id y attack_subtype_id', function () {
    $admin = motorUser('admin');

    $range = publicAttackRange();
    $subtype = publicAttackSubtype();

    $melee = publicAbility(['name' => ['es' => 'Tajo', 'en' => 'Slice'], 'attack_range_id' => $range->id]);
    $corte = publicAbility(['name' => ['es' => 'Cuchillada', 'en' => 'Stab'], 'attack_subtype_id' => $subtype->id]);
    publicAbility(['name' => ['es' => 'Aullido', 'en' => 'Howl']]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/hero-abilities?attack_range_id={$range->id}")
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$melee->id]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin/hero-abilities?attack_subtype_id={$subtype->id}")
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$corte->id]);
});

it('filtra las habilidades por area con 1/0 y lo ignora si no viene', function () {
    $admin = motorUser('admin');

    $conArea = publicAbility(['name' => ['es' => 'Explosión', 'en' => 'Blast'], 'area' => true]);
    $sinArea = publicAbility(['name' => ['es' => 'Dardo', 'en' => 'Dart'], 'area' => false]);

    $si = $this->actingAs($admin)->getJson('/api/admin/hero-abilities?area=1')->assertOk();
    expect(array_column($si->json('data'), 'id'))->toBe([$conArea->id]);

    $no = $this->actingAs($admin)->getJson('/api/admin/hero-abilities?area=0')->assertOk();
    expect(array_column($no->json('data'), 'id'))->toBe([$sinArea->id]);

    // Ausente (o un valor raro): no filtra
    foreach (['/api/admin/hero-abilities', '/api/admin/hero-abilities?area=raro'] as $url) {
        $response = $this->actingAs($admin)->getJson($url)->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    }
});

it('filtra las habilidades por cost_total (longitud del cost canónico)', function () {
    $admin = motorUser('admin');

    $uno = publicAbility(['name' => ['es' => 'Barato', 'en' => 'Cheap'], 'cost' => 'R']);
    $tres = publicAbility(['name' => ['es' => 'Caro', 'en' => 'Pricey'], 'cost' => 'RGB']);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/hero-abilities?cost_total=1')
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$uno->id]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/hero-abilities?cost_total=3')
        ->assertOk();
    expect(array_column($response->json('data'), 'id'))->toBe([$tres->id]);

    // cost_total=0 (sin coste) no casa con ninguna: el coste es obligatorio
    $response = $this->actingAs($admin)
        ->getJson('/api/admin/hero-abilities?cost_total=0')
        ->assertOk();
    expect($response->json('data'))->toBeEmpty();

    // Fuera de rango (>5) o no numérico: no filtra
    foreach (['/api/admin/hero-abilities?cost_total=6', '/api/admin/hero-abilities?cost_total=raro'] as $url) {
        $response = $this->actingAs($admin)->getJson($url)->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    }
});
