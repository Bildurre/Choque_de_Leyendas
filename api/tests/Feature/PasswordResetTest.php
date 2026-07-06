<?php

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

// Recuperación de contraseña (doc 05): broker estándar; el enlace del correo
// apunta a la SPA pública (motor.frontend.reset_path) con token + email.

it('envía el enlace de recuperación apuntando a la SPA', function () {
    Notification::fake();
    $user = motorUser();

    $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
        ->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_contains($url, config('motor.frontend.app_url'))
            && str_contains($url, '/restablecer?token=')
            && str_contains($url, urlencode($user->email));
    });

    // Email desconocido: misma respuesta genérica (sin revelar si existe).
    $this->postJson('/api/auth/forgot-password', ['email' => 'nadie@edc.test'])
        ->assertOk();
    Notification::assertCount(1);
});

it('restablece la contraseña con el token y rechaza tokens malos', function () {
    $user = motorUser();
    $token = Password::createToken($user);

    // Token malo -> 422 y la contraseña no cambia.
    $this->postJson('/api/auth/reset-password', [
        'token' => 'nope', 'email' => $user->email,
        'password' => 'nuevaClave123', 'password_confirmation' => 'nuevaClave123',
    ])->assertUnprocessable();

    // Token bueno -> restablecida y se puede entrar con la nueva.
    $this->postJson('/api/auth/reset-password', [
        'token' => $token, 'email' => $user->email,
        'password' => 'nuevaClave123', 'password_confirmation' => 'nuevaClave123',
    ])->assertOk();

    $this->postJson('/api/auth/login', [
        'email' => $user->email, 'password' => 'nuevaClave123',
    ])->assertOk();
});
