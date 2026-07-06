<?php

use Edc\Core\Icons\Models\Icon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lista los iconos públicamente para el selector del editor', function () {
    Icon::create(['name' => 'Espada']);

    $this->getJson('/api/icons')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'espada');
});

it('sube un icono SVG desde el admin', function () {
    Storage::fake('public');

    $svg = UploadedFile::fake()->createWithContent('espada.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

    $this->actingAs(motorUser('admin'))->postJson('/api/admin/icons', [
        'name' => 'Espada',
        'image' => $svg,
    ])->assertCreated()->assertJsonPath('data.slug', 'espada');

    expect(Icon::first()->getFirstMedia('image'))->not->toBeNull();
});

it('edita un icono: renombra y sustituye la imagen (opcional)', function () {
    Storage::fake('public');
    $icon = Icon::create(['name' => 'Espada']);

    // Solo el nombre.
    $this->actingAs(motorUser('admin'))->postJson("/api/admin/icons/{$icon->id}", [
        'name' => 'Espada larga',
    ])->assertOk()->assertJsonPath('data.name', 'Espada larga');

    // Nombre + imagen nueva.
    $svg = UploadedFile::fake()->createWithContent('espada.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $this->actingAs(motorUser('admin'))->post("/api/admin/icons/{$icon->id}", [
        'name' => 'Mandoble',
        'image' => $svg,
    ])->assertOk()->assertJsonPath('data.name', 'Mandoble');

    expect($icon->refresh()->getFirstMedia('image'))->not->toBeNull();
});

it('la gestión de iconos exige acceso de admin', function () {
    $this->postJson('/api/admin/icons', ['name' => 'X'])->assertUnauthorized();
    $this->actingAs(motorUser('user'))->postJson('/api/admin/icons', ['name' => 'X'])->assertForbidden();
});

it('borra un icono y su media', function () {
    Storage::fake('public');
    $icon = Icon::create(['name' => 'Espada']);

    $this->actingAs(motorUser('admin'))->deleteJson("/api/admin/icons/{$icon->id}")
        ->assertNoContent();

    expect(Icon::count())->toBe(0);
});
