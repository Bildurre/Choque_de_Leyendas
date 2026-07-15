<?php

use App\Models\LifeCounterMatch;

/*
| Histórico del contador de vidas (herramienta de la web pública): endpoints
| con auth:sanctum para cualquier usuario registrado. Nadie ve ni toca
| partidas ajenas (404 vía scoping por user_id).
*/

/** Estado mínimo verosímil de una partida (el servidor lo trata como opaco). */
function lifeCounterState(array $overrides = []): array
{
    return array_merge([
        'teams' => [
            ['factions' => [], 'heroes' => [['id' => 1, 'name' => 'Aritz', 'health' => 20, 'lives' => 20]]],
            ['factions' => [], 'heroes' => [['id' => 2, 'name' => 'Beltza', 'health' => 18, 'lives' => 18]]],
        ],
    ], $overrides);
}

/** Partida persistida de un usuario (activa por defecto). */
function lifeCounterMatch(int $userId, array $overrides = []): LifeCounterMatch
{
    return LifeCounterMatch::create([
        'user_id' => $userId,
        'state' => $overrides['state'] ?? lifeCounterState(),
        'status' => $overrides['status'] ?? 'active',
    ]);
}

it('exige autenticación en todos los endpoints', function () {
    $match = lifeCounterMatch(motorUser()->id);

    $this->getJson('/api/life-counter/matches')->assertUnauthorized();
    $this->postJson('/api/life-counter/matches', ['state' => lifeCounterState()])->assertUnauthorized();
    $this->putJson("/api/life-counter/matches/{$match->id}", ['state' => lifeCounterState()])->assertUnauthorized();
    $this->postJson("/api/life-counter/matches/{$match->id}/finish")->assertUnauthorized();
});

it('crea una partida activa con el estado inicial', function () {
    $user = motorUser();
    $state = lifeCounterState();

    $response = $this->actingAs($user)
        ->postJson('/api/life-counter/matches', ['state' => $state])
        ->assertCreated();

    expect($response->json('data.status'))->toBe('active')
        ->and($response->json('data.state'))->toBe($state);

    $match = LifeCounterMatch::findOrFail($response->json('data.id'));
    expect($match->user_id)->toBe($user->id);
});

it('valida que el estado llegue y sea un objeto', function () {
    $user = motorUser();

    $this->actingAs($user)->postJson('/api/life-counter/matches', [])
        ->assertUnprocessable()->assertJsonValidationErrors('state');

    $this->actingAs($user)->postJson('/api/life-counter/matches', ['state' => 'no-json'])
        ->assertUnprocessable()->assertJsonValidationErrors('state');
});

it('lista solo las partidas propias, las más recientes primero', function () {
    $user = motorUser();
    $other = motorUser();

    $vieja = lifeCounterMatch($user->id, ['status' => 'finished']);
    $vieja->forceFill(['updated_at' => now()->subDay()])->save();
    $activa = lifeCounterMatch($user->id);
    lifeCounterMatch($other->id); // ajena: fuera del listado

    $response = $this->actingAs($user)->getJson('/api/life-counter/matches')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$activa->id, $vieja->id]);
});

it('actualiza el estado de una partida activa propia', function () {
    $user = motorUser();
    $match = lifeCounterMatch($user->id);
    $state = lifeCounterState(['round' => 3]);

    $this->actingAs($user)
        ->putJson("/api/life-counter/matches/{$match->id}", ['state' => $state])
        ->assertOk()
        ->assertJsonPath('data.state.round', 3);

    expect($match->fresh()->state)->toBe($state);
});

it('no deja actualizar una partida terminada (solo lectura)', function () {
    $user = motorUser();
    $match = lifeCounterMatch($user->id, ['status' => 'finished']);

    $this->actingAs($user)
        ->putJson("/api/life-counter/matches/{$match->id}", ['state' => lifeCounterState()])
        ->assertNotFound();
});

it('termina una partida propia, con estado final opcional', function () {
    $user = motorUser();
    $match = lifeCounterMatch($user->id);
    $final = lifeCounterState(['winner' => 0]);

    $this->actingAs($user)
        ->postJson("/api/life-counter/matches/{$match->id}/finish", ['state' => $final])
        ->assertOk()
        ->assertJsonPath('data.status', 'finished');

    expect($match->fresh()->state)->toBe($final);

    // Sin body también termina (se queda el último estado guardado)
    $otra = lifeCounterMatch($user->id);
    $this->actingAs($user)
        ->postJson("/api/life-counter/matches/{$otra->id}/finish")
        ->assertOk()
        ->assertJsonPath('data.status', 'finished');
});

it('no ve, actualiza ni termina partidas ajenas', function () {
    $user = motorUser();
    $ajena = lifeCounterMatch(motorUser()->id);

    $this->actingAs($user)
        ->putJson("/api/life-counter/matches/{$ajena->id}", ['state' => lifeCounterState()])
        ->assertNotFound();
    $this->actingAs($user)
        ->postJson("/api/life-counter/matches/{$ajena->id}/finish")
        ->assertNotFound();

    expect($this->actingAs($user)->getJson('/api/life-counter/matches')->json('data'))->toBeEmpty();
});
