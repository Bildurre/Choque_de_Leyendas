<?php

use Edc\Core\Backup\Jobs\RunBackupJob;
use Edc\Core\Backup\MotorBackup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

// Copias de seguridad (doc 06, DC-16): crear, listar, descargar y borrar
// desde el admin; solo manage-web (los editores no entran).

beforeEach(function () {
    // Disco de copias falso y una fuente mínima (la BBDD de tests es sqlite
    // en memoria: no hay fichero que meter en el zip).
    Storage::fake('backups');
    config([
        'backup.backup.source.files.include' => [base_path('composer.json')],
        'backup.backup.source.databases' => [],
    ]);
});

it('el admin crea, lista, descarga y borra copias de seguridad', function () {
    $admin = motorUser('admin');

    // Crear (síncrono) devuelve el listado con la copia nueva.
    $created = $this->actingAs($admin)->postJson('/api/admin/backups')
        ->assertCreated();
    $file = $created->json('data.0.file');
    expect($file)->toEndWith('.zip')
        ->and($created->json('data.0.size'))->toBeGreaterThan(0);

    // Listar.
    $this->actingAs($admin)->getJson('/api/admin/backups')
        ->assertOk()
        ->assertJsonPath('data.0.file', $file);

    // Descargar.
    $this->actingAs($admin)->get("/api/admin/backups/{$file}/download")
        ->assertOk()
        ->assertDownload($file);

    // Borrar (y un nombre desconocido da 404).
    $this->actingAs($admin)->deleteJson("/api/admin/backups/{$file}")
        ->assertNoContent();
    $this->actingAs($admin)->getJson('/api/admin/backups')
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->actingAs($admin)->deleteJson("/api/admin/backups/{$file}")
        ->assertNotFound();
});

it('con motor.backup.queue la copia manual va en cola (BBDD grandes)', function () {
    config(['motor.backup.queue' => true]);
    $admin = motorUser('admin');

    Queue::fake();
    $this->actingAs($admin)->postJson('/api/admin/backups')
        ->assertAccepted()
        ->assertJsonPath('queued', true);
    Queue::assertPushed(RunBackupJob::class);

    // El job crea el zip de verdad (disco falso del beforeEach).
    (new RunBackupJob)->handle();
    $this->actingAs($admin)->getJson('/api/admin/backups')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('la copia automática se configura desde el admin y la programa el motor', function () {
    $admin = motorUser('admin');

    // Los defaults viajan con el listado.
    $this->actingAs($admin)->getJson('/api/admin/backups')
        ->assertOk()
        ->assertJsonPath('schedule.auto', true)
        ->assertJsonPath('schedule.frequency', 'daily')
        ->assertJsonPath('schedule.time', '03:00');

    // Guardar la configuración (y validación de la hora).
    $this->actingAs($admin)->putJson('/api/admin/backups/schedule', [
        'auto' => true, 'frequency' => 'weekly', 'time' => '04:30', 'weekday' => 7, 'keep_days' => 30,
    ])->assertOk()->assertJsonPath('schedule.keep_days', 30);
    $this->actingAs($admin)->putJson('/api/admin/backups/schedule', [
        'auto' => true, 'frequency' => 'daily', 'time' => 'mediodía', 'weekday' => 1, 'keep_days' => 30,
    ])->assertUnprocessable();

    // El scheduler del motor la aplica: semanal, domingo (7 -> 0) a las 04:30…
    $schedule = new Schedule;
    MotorBackup::schedule($schedule);
    expect(collect($schedule->events())->map(fn ($e) => $e->expression)->all())
        ->toBe(['30 4 * * 0']);

    // …la retención llega a spatie…
    MotorBackup::applyConfig();
    expect(config('backup.cleanup.default_strategy.keep_all_backups_for_days'))->toBe(30);

    // …y desactivada no se programa nada.
    $this->actingAs($admin)->putJson('/api/admin/backups/schedule', [
        'auto' => false, 'frequency' => 'weekly', 'time' => '04:30', 'weekday' => 7, 'keep_days' => 30,
    ])->assertOk();
    $schedule = new Schedule;
    MotorBackup::schedule($schedule);
    expect($schedule->events())->toBeEmpty();
});

it('las copias de seguridad son solo de manage-web', function () {
    $editor = motorUser('editor');

    $this->actingAs($editor)->getJson('/api/admin/backups')->assertForbidden();
    $this->actingAs($editor)->postJson('/api/admin/backups')->assertForbidden();
    $this->actingAs($editor)->putJson('/api/admin/backups/schedule', [])->assertForbidden();
});
