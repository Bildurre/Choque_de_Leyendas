<?php

use Edc\Core\Backup\Jobs\RunBackupJob;
use Edc\Core\Backup\MotorBackup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

// Copias de seguridad (doc 06, DC-16): crear (en cola), listar, subir,
// restaurar, descargar y borrar desde el admin; solo manage-web.

beforeEach(function () {
    // Disco de copias falso y una fuente mínima (la BBDD de tests es sqlite
    // en memoria: no hay fichero que meter en el zip).
    Storage::fake('backups');
    config([
        'backup.backup.source.files.include' => [base_path('composer.json')],
        'backup.backup.source.databases' => [],
    ]);
});

/** Crea un zip temporal con las entradas dadas (nombre => contenido). */
function makeBackupZip(array $entries): string
{
    $path = tempnam(sys_get_temp_dir(), 'backup-test-').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($entries as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    return $path;
}

it('el admin crea (en cola), lista, descarga y borra copias de seguridad', function () {
    $admin = motorUser('admin');

    // Crear va SIEMPRE en cola (202); en tests la cola sync ejecuta el job
    // inline, así que el listado de la respuesta ya trae la copia nueva,
    // con el prefijo manual- (origen) y el pendiente limpiado.
    $created = $this->actingAs($admin)->postJson('/api/admin/backups')
        ->assertAccepted()
        ->assertJsonPath('queued', true);
    $file = $created->json('data.0.file');
    expect($file)->toStartWith('manual-')->toEndWith('.zip')
        ->and($created->json('data.0.size'))->toBeGreaterThan(0)
        ->and($created->json('data.0.origin'))->toBe('manual');

    // Listar (sin copia en curso: el job inline ya limpió el flag).
    $this->actingAs($admin)->getJson('/api/admin/backups')
        ->assertOk()
        ->assertJsonPath('data.0.file', $file)
        ->assertJsonPath('pending', false);

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

it('la copia manual va en cola con flag pending mientras el worker no acaba', function () {
    $admin = motorUser('admin');

    Queue::fake();
    $this->actingAs($admin)->postJson('/api/admin/backups')
        ->assertAccepted()
        ->assertJsonPath('queued', true)
        ->assertJsonPath('pending', true);
    Queue::assertPushed(RunBackupJob::class, fn (RunBackupJob $job) => str_starts_with((string) $job->filename, 'manual-'));

    // Mientras el worker no corre, el listado sigue pendiente.
    $this->actingAs($admin)->getJson('/api/admin/backups')
        ->assertOk()
        ->assertJsonPath('pending', true);

    // El job crea el zip de verdad (disco falso del beforeEach) con el
    // nombre pedido y limpia el pendiente.
    (new RunBackupJob('manual-test.zip'))->handle();
    $this->actingAs($admin)->getJson('/api/admin/backups')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.file', 'manual-test.zip')
        ->assertJsonPath('data.0.origin', 'manual')
        ->assertJsonPath('pending', false);
    expect(Cache::has(MotorBackup::PENDING_CACHE_KEY))->toBeFalse();
});

it('se puede subir una copia (zip con BBDD) y se lista con origen subida', function () {
    $admin = motorUser('admin');

    $zip = makeBackupZip(['db-dumps/sqlite-database.sql' => "CREATE TABLE t (id INTEGER);\n"]);
    $upload = new UploadedFile($zip, 'Mi Copia Vieja.zip', 'application/zip', null, true);

    $response = $this->actingAs($admin)->post('/api/admin/backups/upload', ['file' => $upload], ['Accept' => 'application/json'])
        ->assertCreated();
    expect($response->json('data.0.file'))->toStartWith('upload-mi-copia-vieja-')
        ->and($response->json('data.0.origin'))->toBe('upload');

    // Y las copias del scheduler (nombre-fecha de spatie) salen como auto.
    (new RunBackupJob)->handle();
    $files = collect($this->actingAs($admin)->getJson('/api/admin/backups')->json('data'));
    expect($files->pluck('origin')->sort()->values()->all())->toBe(['auto', 'upload']);

    @unlink($zip);
});

it('la subida valida el archivo: ni no-zip ni zip sin base de datos', function () {
    $admin = motorUser('admin');

    // Extensión que no es zip: lo para la validación.
    $this->actingAs($admin)->post('/api/admin/backups/upload', [
        'file' => UploadedFile::fake()->create('notas.txt', 10, 'text/plain'),
    ], ['Accept' => 'application/json'])->assertUnprocessable();

    // Zip real pero sin dump ni fichero SQLite dentro: 422.
    $zip = makeBackupZip(['leeme.txt' => 'hola']);
    $this->actingAs($admin)->post('/api/admin/backups/upload', [
        'file' => new UploadedFile($zip, 'copia.zip', 'application/zip', null, true),
    ], ['Accept' => 'application/json'])->assertUnprocessable();

    $this->actingAs($admin)->getJson('/api/admin/backups')->assertJsonCount(0, 'data');
    @unlink($zip);
});

it('restaurar una copia importa el dump SQL machacando la BBDD actual', function () {
    $admin = motorUser('admin');

    // Una copia subida cuyo dump deja huella comprobable.
    $dump = "CREATE TABLE restore_probe (id INTEGER PRIMARY KEY, name TEXT);\n"
        ."INSERT INTO restore_probe (name) VALUES ('desde-la-copia');\n";
    $zip = makeBackupZip(['db-dumps/sqlite-database.sql' => $dump]);
    $upload = new UploadedFile($zip, 'restaurable.zip', 'application/zip', null, true);
    $file = $this->actingAs($admin)->post('/api/admin/backups/upload', ['file' => $upload], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data.0.file');

    // Un nombre desconocido da 404 (antes de restaurar: después el
    // esquema actual — roles incluidos — ya no existe).
    $this->actingAs($admin)->postJson('/api/admin/backups/no-existe.zip/restore')
        ->assertNotFound();

    // Restaurar: el esquema actual se vacía y el dump se importa.
    $this->actingAs($admin)->postJson("/api/admin/backups/{$file}/restore")
        ->assertOk()
        ->assertJsonPath('restored', 'db-dumps/sqlite-database.sql');
    expect(DB::table('restore_probe')->value('name'))->toBe('desde-la-copia');

    @unlink($zip);
});

it('restaurar una copia sin BBDD dentro da 422', function () {
    $admin = motorUser('admin');

    // Un zip sin BBDD colado directamente en el destino (una copia vieja de
    // solo ficheros): no puede restaurarse.
    $zip = makeBackupZip(['solo-ficheros.txt' => 'nada de BBDD']);
    Storage::disk('backups')->putFileAs(config('backup.backup.name'), $zip, 'upload-sin-bbdd.zip');

    $this->actingAs($admin)->postJson('/api/admin/backups/upload-sin-bbdd.zip/restore')
        ->assertUnprocessable();

    @unlink($zip);
});

it('con la BBDD sqlite en fichero, restaurar sustituye el fichero', function () {
    $admin = motorUser('admin');

    // BBDD "actual" en un fichero temporal (la conexión de la suite sigue
    // en el :memory: ya abierto; el restaurador solo copia bytes).
    $dbFile = tempnam(sys_get_temp_dir(), 'motor-db-').'.sqlite';
    file_put_contents($dbFile, 'BBDD-VIEJA');
    config(['database.connections.sqlite.database' => $dbFile]);

    $zip = makeBackupZip(['datos/database.sqlite' => 'BBDD-DE-LA-COPIA']);
    Storage::disk('backups')->putFileAs(config('backup.backup.name'), $zip, 'upload-fichero.zip');

    $this->actingAs($admin)->postJson('/api/admin/backups/upload-fichero.zip/restore')
        ->assertOk()
        ->assertJsonPath('restored', 'datos/database.sqlite');
    expect(file_get_contents($dbFile))->toBe('BBDD-DE-LA-COPIA');

    @unlink($dbFile);
    @unlink($zip);
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
    $this->actingAs($editor)->post('/api/admin/backups/upload')->assertForbidden();
    $this->actingAs($editor)->postJson('/api/admin/backups/algo.zip/restore')->assertForbidden();
    $this->actingAs($editor)->putJson('/api/admin/backups/schedule', [])->assertForbidden();
});
