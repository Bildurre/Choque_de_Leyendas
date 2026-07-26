<?php

// Estadísticas del single de mazo: solo admin, cartas por tipo y curva de
// coste PONDERADAS por copias del pivot, héroes por clase y superclase (sin
// copias: cada uno cuenta 1) y nombres localizados al locale de la petición.

require_once __DIR__.'/Public/Helpers.php';

beforeEach(function () {
    $this->withHeader('Accept-Language', 'es');
});

it('devuelve las estadísticas del mazo ponderadas por copias', function () {
    $admin = motorUser('admin');

    $deck = publicDeck(['is_published' => false]);
    $slug = $deck->getTranslation('slug', 'es');

    $attack = publicCardType(['name' => ['es' => 'Ataque', 'en' => 'Attack']]);
    $support = publicCardType(['name' => ['es' => 'Apoyo', 'en' => 'Support']]);

    // 3 cartas: RRG (3 dados) ×3 copias, B (1 dado) ×2 y sin coste ×1.
    $deck->cards()->attach([
        publicCard(['name' => ['es' => 'Tajo'], 'card_type_id' => $attack->id, 'cost' => 'RRG'])->id => ['copies' => 3],
        publicCard(['name' => ['es' => 'Flecha'], 'card_type_id' => $attack->id, 'cost' => 'B'])->id => ['copies' => 2],
        publicCard(['name' => ['es' => 'Vendaje'], 'card_type_id' => $support->id, 'cost' => null])->id => ['copies' => 1],
    ]);

    // 3 héroes: dos de la misma clase (misma superclase) y uno de otra.
    $warrior = publicHeroClass([
        'name' => ['es' => 'Guerrero'],
        'superclass_name' => ['es' => 'Luchador'],
    ]);
    $mage = publicHeroClass([
        'name' => ['es' => 'Mago'],
        'superclass_name' => ['es' => 'Arcano'],
    ]);
    $deck->heroes()->attach([
        publicHero(['name' => ['es' => 'Aritz'], 'hero_class_id' => $warrior->id])->id,
        publicHero(['name' => ['es' => 'Beñat'], 'hero_class_id' => $warrior->id])->id,
        publicHero(['name' => ['es' => 'Miren'], 'hero_class_id' => $mage->id])->id,
    ]);

    $response = $this->actingAs($admin)->getJson("/api/admin/faction-decks/{$slug}/stats")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'cards' => ['total', 'by_type', 'cost_curve'],
                'heroes' => ['total', 'by_class', 'by_superclass'],
            ],
        ]);

    // Totales en copias (3+2+1 cartas) y en héroes (cada uno cuenta 1).
    $response->assertJsonPath('data.cards.total', 6)
        ->assertJsonPath('data.heroes.total', 3);

    // Cartas por tipo ponderadas por copias, ordenadas por cantidad.
    $byType = collect($response->json('data.cards.by_type'))->pluck('count', 'name');
    expect($byType['Ataque'])->toBe(5)->and($byType['Apoyo'])->toBe(1);

    // Curva de coste 0..5 dados, también en copias.
    $curve = collect($response->json('data.cards.cost_curve'))->pluck('count', 'dice');
    expect($curve->keys()->all())->toBe([0, 1, 2, 3, 4, 5])
        ->and($curve[0])->toBe(1)
        ->and($curve[1])->toBe(2)
        ->and($curve[3])->toBe(3);

    // Héroes por clase y por superclase (agregada desde la clase).
    $byClass = collect($response->json('data.heroes.by_class'))->pluck('count', 'name');
    $bySuperclass = collect($response->json('data.heroes.by_superclass'))->pluck('count', 'name');
    expect($byClass['Guerrero'])->toBe(2)->and($byClass['Mago'])->toBe(1)
        ->and($bySuperclass['Luchador'])->toBe(2)->and($bySuperclass['Arcano'])->toBe(1);
});

it('localiza los nombres al locale de la petición', function () {
    $admin = motorUser('admin');

    $deck = publicDeck(['is_published' => false]);
    $slug = $deck->getTranslation('slug', 'es');

    $type = publicCardType(['name' => ['es' => 'Técnica', 'en' => 'Technique']]);
    $deck->cards()->attach([
        publicCard(['card_type_id' => $type->id])->id => ['copies' => 2],
    ]);

    $this->actingAs($admin)->getJson("/api/admin/faction-decks/{$slug}/stats?locale=en")
        ->assertOk()
        ->assertJsonPath('data.cards.by_type.0.name', 'Technique')
        ->assertJsonPath('data.cards.by_type.0.count', 2);
});

it('solo los admin acceden a las estadísticas del mazo', function () {
    $deck = publicDeck();
    $slug = $deck->getTranslation('slug', 'es');

    $this->getJson("/api/admin/faction-decks/{$slug}/stats")->assertUnauthorized();

    $user = motorUser('user');
    $this->actingAs($user)->getJson("/api/admin/faction-decks/{$slug}/stats")->assertForbidden();
});
