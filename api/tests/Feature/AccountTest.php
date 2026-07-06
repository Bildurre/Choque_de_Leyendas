<?php

use Illuminate\Support\Facades\Hash;

it('muestra los datos de cuenta del usuario autenticado', function () {
    $user = motorUser();

    $this->actingAs($user)->getJson('/api/account')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('actualiza nombre y email de la cuenta', function () {
    $user = motorUser();

    $this->actingAs($user)->putJson('/api/account', [
        'name' => 'Nuevo Nombre',
        'email' => 'nuevo@example.com',
    ])->assertOk()->assertJsonPath('data.name', 'Nuevo Nombre');

    expect($user->fresh()->email)->toBe('nuevo@example.com');
});

it('no permite usar el email de otro usuario', function () {
    $user = motorUser();
    $other = motorUser();

    $this->actingAs($user)->putJson('/api/account', [
        'name' => $user->name,
        'email' => $other->email,
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('cambia la contraseña con la actual correcta', function () {
    $user = motorUser();

    $this->actingAs($user)->putJson('/api/account/password', [
        'current_password' => 'password',
        'password' => 'nueva-secreta-1',
        'password_confirmation' => 'nueva-secreta-1',
    ])->assertOk();

    expect(Hash::check('nueva-secreta-1', $user->fresh()->password))->toBeTrue();
});

it('rechaza el cambio si la contraseña actual no es correcta', function () {
    $user = motorUser();

    $this->actingAs($user)->putJson('/api/account/password', [
        'current_password' => 'incorrecta',
        'password' => 'nueva-secreta-1',
        'password_confirmation' => 'nueva-secreta-1',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
});
