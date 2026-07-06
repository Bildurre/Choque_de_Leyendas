<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (config('motor.auth.roles') as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('registra un usuario con rol user y devuelve token', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Egoi',
        'email' => 'egoi@example.com',
        'password' => 'secret-123',
        'password_confirmation' => 'secret-123',
        'privacy' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.email', 'egoi@example.com')
        ->assertJsonPath('user.roles.0', 'user')
        ->assertJsonPath('user.can_access_admin', false)
        ->assertJsonStructure(['token']);
});

it('exige aceptar la protección de datos para registrarse', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Egoi',
        'email' => 'egoi@example.com',
        'password' => 'secret-123',
        'password_confirmation' => 'secret-123',
    ])->assertUnprocessable()->assertJsonValidationErrors('privacy');
});

it('guarda el locale del registro y lo usa como idioma preferido', function () {
    $this->postJson('/api/auth/register?locale=eu', [
        'name' => 'Egoi',
        'email' => 'egoi@example.com',
        'password' => 'secret-123',
        'password_confirmation' => 'secret-123',
        'privacy' => true,
    ])->assertCreated();

    $user = User::firstWhere('email', 'egoi@example.com');
    expect($user->locale)->toBe('eu')
        ->and($user->preferredLocale())->toBe('eu');
});

it('rechaza el registro cuando el juego es solo-invitación', function () {
    config(['motor.auth.registration' => 'invite']);

    $this->postJson('/api/auth/register', [
        'name' => 'Egoi',
        'email' => 'egoi@example.com',
        'password' => 'secret-123',
        'password_confirmation' => 'secret-123',
    ])->assertForbidden();
});

it('hace login con credenciales válidas', function () {
    $user = motorUser();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()->assertJsonStructure(['user', 'token']);
});

it('rechaza credenciales incorrectas', function () {
    $user = motorUser();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'nope-nope',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('devuelve el usuario autenticado en /auth/me', function () {
    $user = motorUser('admin');

    $this->actingAs($user)->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.can_access_admin', true);
});

it('cierra sesión revocando el token actual', function () {
    $user = motorUser();
    $token = $user->createToken('bgm')->plainTextToken;

    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

it('traspasa la sesión entre las SPA con un código de un solo uso (handoff)', function () {
    // Pedir un código exige sesión (antes de actingAs: se queda pegado).
    $this->postJson('/api/auth/handoff')->assertUnauthorized();

    $user = motorUser('admin');
    $code = $this->actingAs($user)->postJson('/api/auth/handoff')
        ->assertOk()
        ->json('code');

    // El canje es público (el código ES la credencial)…
    $response = $this->postJson('/api/auth/handoff/consume', ['code' => $code])->assertOk();
    expect($response->json('user.id'))->toBe($user->id)
        ->and($response->json('token'))->toBeString();

    // …pero de UN SOLO USO, y un código inventado no vale.
    $this->postJson('/api/auth/handoff/consume', ['code' => $code])->assertUnauthorized();
    $this->postJson('/api/auth/handoff/consume', ['code' => 'nope-nope'])->assertUnauthorized();
});

it('permite el admin a admin y editor pero no a user ni invitados', function () {
    $this->getJson('/api/admin/ping')->assertUnauthorized();

    $this->actingAs(motorUser('user'))->getJson('/api/admin/ping')->assertForbidden();
    $this->actingAs(motorUser('editor'))->getJson('/api/admin/ping')->assertOk();
    $this->actingAs(motorUser('admin'))->getJson('/api/admin/ping')->assertOk();
});
