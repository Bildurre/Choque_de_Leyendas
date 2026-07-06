<?php

use App\Models\User;

// Gestión de usuarios y permisos (doc 05): CRUD básico protegido por
// manage-users, y separación admin/editor — el editor gestiona solo el
// juego (manage-game), no la web ni los usuarios.

it('el admin crea, edita y borra usuarios', function () {
    $admin = motorUser('admin');

    $created = $this->actingAs($admin)->postJson('/api/admin/users', [
        'name' => 'Nueva Editora',
        'email' => 'editora@edc.test',
        'password' => 'secreta123',
        'role' => 'editor',
    ])->assertCreated();

    $id = $created->json('data.id');
    expect($created->json('data.roles'))->toBe(['editor'])
        ->and($created->json('data.permissions'))->toBe(['manage-game']);

    // Listado con búsqueda.
    $this->actingAs($admin)->getJson('/api/admin/users?search=editora')
        ->assertOk()
        ->assertJsonPath('data.0.email', 'editora@edc.test');

    // Edición: cambia el rol (y la contraseña vacía no se toca).
    $this->actingAs($admin)->putJson("/api/admin/users/{$id}", [
        'name' => 'Nueva Admin',
        'email' => 'editora@edc.test',
        'password' => '',
        'role' => 'admin',
    ])->assertOk()->assertJsonPath('data.roles.0', 'admin');

    // Email duplicado -> 422; rol desconocido -> 422.
    $this->actingAs($admin)->postJson('/api/admin/users', [
        'name' => 'Dupe', 'email' => 'editora@edc.test', 'password' => 'secreta123', 'role' => 'editor',
    ])->assertUnprocessable();
    $this->actingAs($admin)->postJson('/api/admin/users', [
        'name' => 'Rey', 'email' => 'rey@edc.test', 'password' => 'secreta123', 'role' => 'rey',
    ])->assertUnprocessable();

    $this->actingAs($admin)->deleteJson("/api/admin/users/{$id}")->assertNoContent();
    expect(User::find($id))->toBeNull();
});

it('el admin verifica y desverifica el email de un usuario', function () {
    $admin = motorUser('admin');
    $user = motorUser('user');
    expect($user->email_verified_at)->not->toBeNull();

    // Desverifica…
    $this->actingAs($admin)->postJson("/api/admin/users/{$user->id}/toggle-verified")
        ->assertOk()
        ->assertJsonPath('data.email_verified', false);
    expect($user->fresh()->email_verified_at)->toBeNull();

    // …y vuelve a verificar.
    $this->actingAs($admin)->postJson("/api/admin/users/{$user->id}/toggle-verified")
        ->assertOk()
        ->assertJsonPath('data.email_verified', true);
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('nadie se borra a sí mismo ni se cambia su propio rol', function () {
    $admin = motorUser('admin');

    $this->actingAs($admin)->deleteJson("/api/admin/users/{$admin->id}")
        ->assertStatus(422);

    // El PUT sobre uno mismo guarda datos pero ignora el rol.
    $this->actingAs($admin)->putJson("/api/admin/users/{$admin->id}", [
        'name' => 'Yo', 'email' => $admin->email, 'password' => '', 'role' => 'user',
    ])->assertOk()->assertJsonPath('data.roles.0', 'admin');
});

it('el editor gestiona el juego pero no la web, la configuración ni los usuarios', function () {
    $editor = motorUser('editor');

    // Juego: sí (previews, pdfs).
    $this->actingAs($editor)->getJson('/api/admin/previews')->assertOk();
    $this->actingAs($editor)->getJson('/api/admin/pdfs/exports')->assertOk();

    // Web y usuarios: no.
    $this->actingAs($editor)->getJson('/api/admin/pages')->assertForbidden();
    $this->actingAs($editor)->getJson('/api/admin/block-types')->assertForbidden();
    $this->actingAs($editor)->getJson('/api/admin/settings/site')->assertForbidden();
    $this->actingAs($editor)->putJson('/api/admin/settings/site', [])->assertForbidden();
    $this->actingAs($editor)->getJson('/api/admin/users')->assertForbidden();

    // El admin llega a todo.
    $admin = motorUser('admin');
    $this->actingAs($admin)->getJson('/api/admin/pages')->assertOk();
    $this->actingAs($admin)->getJson('/api/admin/users')->assertOk();
});
