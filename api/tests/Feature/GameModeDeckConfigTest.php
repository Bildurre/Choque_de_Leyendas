<?php

// Fusión "configuración de mazos" → "modo de juego": límites en el propio
// modo, invariante de un único por defecto y el constructor de mazos
// (héroes y cartas con copias, acotados por facción).

use App\Models\GameMode;

require_once __DIR__.'/Public/Helpers.php';

beforeEach(function () {
    $this->withHeader('Accept-Language', 'es');
});

/** Payload completo de modo de juego (nombre + configuración de mazos). */
function gameModePayload(array $overrides = []): array
{
    return array_merge([
        'name' => ['es' => 'Modo de prueba'],
        'description' => ['es' => 'Descripción'],
        'min_cards' => 30,
        'max_cards' => 40,
        'max_copies_per_card' => 2,
        'required_heroes' => 5,
    ], $overrides);
}

it('guarda la configuración de mazos en el propio modo', function () {
    $admin = motorUser('admin');

    $response = $this->actingAs($admin)
        ->postJson('/api/admin/game-modes', gameModePayload([
            'min_cards' => 20, 'max_cards' => 30, 'max_copies_per_card' => 3, 'required_heroes' => 1,
        ]))
        ->assertCreated();

    expect($response->json('data.min_cards'))->toBe(20)
        ->and($response->json('data.max_cards'))->toBe(30)
        ->and($response->json('data.max_copies_per_card'))->toBe(3)
        ->and($response->json('data.required_heroes'))->toBe(1)
        // El primer modo pasa a ser el por defecto aunque no se marque
        ->and($response->json('data.is_default'))->toBeTrue();

    // La configuración exige coherencia (máximo >= mínimo)
    $this->actingAs($admin)
        ->postJson('/api/admin/game-modes', gameModePayload(['min_cards' => 40, 'max_cards' => 30]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['max_cards']);
});

it('marcar un modo por defecto desmarca el anterior (exactamente uno)', function () {
    $admin = motorUser('admin');

    $first = $this->actingAs($admin)
        ->postJson('/api/admin/game-modes', gameModePayload(['name' => ['es' => 'Clásico']]))
        ->json('data');
    $second = $this->actingAs($admin)
        ->postJson('/api/admin/game-modes', gameModePayload(['name' => ['es' => 'Arena'], 'is_default' => true]))
        ->json('data');

    expect($second['is_default'])->toBeTrue()
        ->and(GameMode::find($first['id'])->is_default)->toBeFalse()
        ->and(GameMode::where('is_default', true)->count())->toBe(1);

    // El por defecto no se puede desmarcar a sí mismo…
    $this->actingAs($admin)
        ->putJson("/api/admin/game-modes/{$second['id']}", gameModePayload(['name' => ['es' => 'Arena'], 'is_default' => false]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_default']);

    // …ni borrarse (primero hay que marcar otro)
    $this->actingAs($admin)
        ->deleteJson("/api/admin/game-modes/{$second['id']}")
        ->assertStatus(422);
});

it('sirve en público el modo por defecto con su configuración', function () {
    $mode = publicGameMode();
    $mode->update(['is_default' => true, 'required_heroes' => 4]);

    $this->getJson('/api/game-modes/default')
        ->assertOk()
        ->assertJsonPath('data.id', $mode->id)
        ->assertJsonPath('data.required_heroes', 4);
});

it('publicar un mazo valida contra la configuración de su modo', function () {
    $admin = motorUser('admin');

    $mode = publicGameMode();
    $mode->update(['min_cards' => 1, 'max_cards' => 40, 'max_copies_per_card' => 2, 'required_heroes' => 2]);

    $deck = publicDeck(['game_mode_id' => $mode->id, 'is_published' => false]);
    $card = publicCard();
    $deck->cards()->attach($card->id, ['copies' => 2]);

    // Sin los 2 héroes exigidos: 422 con el error localizable
    $slug = $deck->getTranslation('slug', 'es');
    $response = $this->actingAs($admin)
        ->postJson("/api/admin/faction-decks/{$slug}/toggle-published")
        ->assertStatus(422);
    expect(collect($response->json('errors.deck'))->pluck('key'))
        ->toContain('factionDecks.validation.requiredHeroes');

    // Con dos copias de un héroe (suman como total) ya publica
    $deck->heroes()->attach(publicHero()->id, ['copies' => 2]);
    $this->actingAs($admin)
        ->postJson("/api/admin/faction-decks/{$slug}/toggle-published")
        ->assertOk()
        ->assertJsonPath('data.is_published', true);
});

it('guarda los héroes del mazo con sus copias y los totales las suman', function () {
    $admin = motorUser('admin');

    $deck = publicDeck(['is_published' => false]);
    $hero = publicHero();
    $slug = $deck->getTranslation('slug', 'es');

    $this->actingAs($admin)
        ->putJson("/api/admin/faction-decks/{$slug}/heroes", [
            'items' => [['hero_id' => $hero->id, 'copies' => 3]],
        ])
        ->assertOk()
        ->assertJsonPath('data.heroes.0.copies', 3)
        ->assertJsonPath('data.total_heroes', 3);

    // Copias inválidas (mínimo 1), tanto en héroes como en cartas
    $this->actingAs($admin)
        ->putJson("/api/admin/faction-decks/{$slug}/heroes", [
            'items' => [['hero_id' => $hero->id, 'copies' => 0]],
        ])
        ->assertStatus(422);
    $this->actingAs($admin)
        ->putJson("/api/admin/faction-decks/{$slug}/cards", [
            'items' => [['card_id' => publicCard()->id, 'copies' => 0]],
        ])
        ->assertStatus(422);
});

it('acota héroes y cartas por varias facciones (faction_ids del editor)', function () {
    $admin = motorUser('admin');

    $factionA = publicFaction(['name' => ['es' => 'Alianza Norte'], 'is_published' => false]);
    $factionB = publicFaction(['name' => ['es' => 'Horda Sur'], 'is_published' => false]);
    $factionC = publicFaction(['name' => ['es' => 'Neutrales'], 'is_published' => false]);

    $heroA = publicHero(['name' => ['es' => 'Aritz'], 'faction_id' => $factionA->id]);
    publicHero(['name' => ['es' => 'Bruno'], 'faction_id' => $factionC->id]);
    $cardB = publicCard(['name' => ['es' => 'Espada'], 'faction_id' => $factionB->id]);
    publicCard(['name' => ['es' => 'Escudo'], 'faction_id' => $factionC->id]);

    $heroes = $this->actingAs($admin)
        ->getJson('/api/admin/heroes?faction_ids[]='.$factionA->id.'&faction_ids[]='.$factionB->id)
        ->assertOk()
        ->json('data');
    expect(collect($heroes)->pluck('id'))->toContain($heroA->id)
        ->and(collect($heroes)->pluck('faction_id')->unique()->diff([$factionA->id, $factionB->id]))->toBeEmpty();

    $cards = $this->actingAs($admin)
        ->getJson('/api/admin/cards?faction_ids[]='.$factionA->id.'&faction_ids[]='.$factionB->id)
        ->assertOk()
        ->json('data');
    expect(collect($cards)->pluck('id'))->toContain($cardB->id)
        ->and(collect($cards)->pluck('faction_id')->unique()->diff([$factionA->id, $factionB->id]))->toBeEmpty();
});
