<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (config('motor.auth.roles') as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function verificationUrl(User $user): string
{
    return URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );
}

it('envía el correo de verificación al registrarse', function () {
    Notification::fake();

    $this->postJson('/api/auth/register', [
        'name' => 'Egoi',
        'email' => 'egoi@example.com',
        'password' => 'secret-123',
        'password_confirmation' => 'secret-123',
        'privacy' => true,
    ])->assertCreated()->assertJsonPath('user.email_verified', false);

    Notification::assertSentTo(
        User::where('email', 'egoi@example.com')->first(),
        VerifyEmail::class,
    );
});

it('los correos salen en el idioma del usuario (preferredLocale)', function () {
    // El usuario guarda su idioma al registrarse/loguearse; el sistema de
    // notificaciones renderiza con ese locale (HasLocalePreference). Aquí se
    // comprueba la pieza del motor: preferredLocale + traducciones JSON.
    $user = User::factory()->unverified()->create(['locale' => 'eu']);
    expect($user->preferredLocale())->toBe('eu');

    $original = app()->getLocale();
    app()->setLocale($user->preferredLocale());
    $subject = (new VerifyEmail)->toMail($user)->subject;
    app()->setLocale($original);

    expect($subject)->toBe('Egiaztatu zure helbide elektronikoa')
        ->and(Lang::get('Verify your email address', [], 'es'))
        ->toBe('Verifica tu dirección de correo');
});

it('verifica el email con el enlace firmado y redirige a la app', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrl($user))
        ->assertRedirect(config('motor.frontend.app_url').config('motor.frontend.verified_path'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rechaza un enlace con hash de otro email', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('otro@example.com')],
    );

    $this->get($url)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rechaza un enlace sin firma válida', function () {
    $user = User::factory()->unverified()->create();

    $this->get("/api/auth/verify-email/{$user->id}/".sha1($user->email))
        ->assertForbidden();
});

it('reenvía el correo de verificación a quien no está verificado', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->postJson('/api/auth/email/verification-notification')
        ->assertOk();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('no reenvía nada si ya está verificado', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/auth/email/verification-notification')
        ->assertOk()
        ->assertJsonPath('message', __('motor::motor.already_verified'));

    Notification::assertNothingSent();
});

it('cambiar el email de la cuenta invalida la verificación y reenvía el correo', function () {
    Notification::fake();
    $user = User::factory()->create();
    expect($user->hasVerifiedEmail())->toBeTrue();

    $this->actingAs($user)->putJson('/api/account', [
        'name' => $user->name,
        'email' => 'nuevo@example.com',
    ])->assertOk()->assertJsonPath('data.email_verified', false);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('no toca la verificación si el email no cambia', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/api/account', [
        'name' => 'Otro nombre',
        'email' => $user->email,
    ])->assertOk()->assertJsonPath('data.email_verified', true);

    Notification::assertNothingSent();
});
