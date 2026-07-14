<?php

// Campos de dominio obligatorios: facción/raza/clase/género en héroes,
// superclase en clases de héroe y modo de juego + facciones en mazos.

require_once __DIR__.'/Public/Helpers.php';

beforeEach(function () {
    $this->withHeader('Accept-Language', 'es');
});

/** Payload mínimo válido de héroe (se sobreescribe por test). */
function heroPayload(array $overrides = []): array
{
    return array_merge([
        'name' => ['es' => 'Héroe de prueba'],
        'faction_id' => publicFaction(['is_published' => false])->id,
        'hero_race_id' => publicHeroRace()->id,
        'hero_class_id' => publicHeroClass()->id,
        'gender' => 'female',
        'agility' => 2,
        'mental' => 2,
        'will' => 2,
        'strength' => 2,
        'armor' => 2,
        'is_published' => '0',
    ], $overrides);
}

it('exige facción, raza, clase y género al guardar un héroe', function () {
    $admin = motorUser('admin');

    $payload = heroPayload();
    unset($payload['faction_id'], $payload['hero_race_id'], $payload['hero_class_id'], $payload['gender']);

    $this->actingAs($admin)
        ->postJson('/api/admin/heroes', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['faction_id', 'hero_race_id', 'hero_class_id', 'gender']);

    // Completo se crea con normalidad
    $response = $this->actingAs($admin)
        ->postJson('/api/admin/heroes', heroPayload())
        ->assertCreated();
    expect($response->json('data.faction_id'))->not->toBeNull()
        ->and($response->json('data.hero_race_id'))->not->toBeNull()
        ->and($response->json('data.hero_class_id'))->not->toBeNull()
        ->and($response->json('data.gender'))->toBe('female');

    // Al editar tampoco se pueden vaciar
    $slug = $response->json('data.slug.es');
    $edit = heroPayload(['name' => ['es' => 'Héroe editado']]);
    unset($edit['hero_race_id']);

    $this->actingAs($admin)
        ->putJson("/api/admin/heroes/{$slug}", $edit)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['hero_race_id']);
});

it('exige superclase al guardar una clase de héroe', function () {
    $admin = motorUser('admin');

    $this->actingAs($admin)
        ->postJson('/api/admin/hero-classes', ['name' => ['es' => 'Gladiador']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['hero_superclass_id']);

    $class = publicHeroClass(); // trae superclase propia
    $response = $this->actingAs($admin)
        ->postJson('/api/admin/hero-classes', [
            'name' => ['es' => 'Gladiador'],
            'hero_superclass_id' => $class->hero_superclass_id,
        ])
        ->assertCreated();
    expect($response->json('data.hero_superclass_id'))->toBe($class->hero_superclass_id);

    // Al editar tampoco se puede vaciar
    $this->actingAs($admin)
        ->putJson('/api/admin/hero-classes/'.$response->json('data.id'), [
            'name' => ['es' => 'Gladiador'],
            'hero_superclass_id' => null,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['hero_superclass_id']);
});

it('exige modo de juego y al menos una facción al guardar un mazo', function () {
    $admin = motorUser('admin');

    $this->actingAs($admin)
        ->postJson('/api/admin/faction-decks', ['name' => ['es' => 'Mazo cojo']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['game_mode_id', 'faction_ids']);

    // Con facciones vacías ('' del form) también se rechaza
    $this->actingAs($admin)
        ->postJson('/api/admin/faction-decks', [
            'name' => ['es' => 'Mazo cojo'],
            'game_mode_id' => publicGameMode()->id,
            'faction_ids' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['faction_ids']);

    $faction = publicFaction(['is_published' => false]);
    $response = $this->actingAs($admin)
        ->postJson('/api/admin/faction-decks', [
            'name' => ['es' => 'Mazo completo'],
            'game_mode_id' => publicGameMode()->id,
            'faction_ids' => [$faction->id],
        ])
        ->assertCreated();
    expect($response->json('data.game_mode_id'))->not->toBeNull()
        ->and($response->json('data.factions.0.id'))->toBe($faction->id);

    // Al editar tampoco se pueden quitar todas las facciones
    $slug = $response->json('data.slug.es');
    $this->actingAs($admin)
        ->putJson("/api/admin/faction-decks/{$slug}", [
            'name' => ['es' => 'Mazo completo'],
            'game_mode_id' => publicGameMode()->id,
            'faction_ids' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['faction_ids']);
});
