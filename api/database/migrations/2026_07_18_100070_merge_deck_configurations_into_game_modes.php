<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fusión "configuración de mazo" → "modo de juego": cada modo lleva ahora sus
 * límites de construcción (antes tabla aparte deck_attributes_configurations)
 * y exactamente un modo es el por defecto (is_default), que sustituye a la
 * antigua configuración genérica sin modo.
 *
 * Migración de datos: cada configuración CON modo vuelca sus valores en su
 * modo; la genérica (sin modo) vuelca en el modo por defecto. El por defecto
 * es el modo llamado "Clásico"/"Estándar" (nombre es, sin distinción de
 * mayúsculas) o, si no existe, el de id más bajo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_modes', function (Blueprint $table) {
            $table->integer('min_cards')->default(30);
            $table->integer('max_cards')->default(40);
            $table->integer('max_copies_per_card')->default(2);
            $table->unsignedTinyInteger('required_heroes')->default(0);
            $table->boolean('is_default')->default(false);
        });

        $defaultId = $this->pickDefaultModeId();

        // La configuración genérica primero (al modo por defecto); las
        // específicas después, para que pisen a la genérica si coinciden.
        $configs = DB::table('deck_attributes_configurations')
            ->orderByRaw('case when game_mode_id is null then 0 else 1 end')
            ->get();

        foreach ($configs as $config) {
            $modeId = $config->game_mode_id ?? $defaultId;
            if ($modeId === null) {
                continue;
            }
            DB::table('game_modes')->where('id', $modeId)->update([
                'min_cards' => $config->min_cards,
                'max_cards' => $config->max_cards,
                'max_copies_per_card' => $config->max_copies_per_card,
                'required_heroes' => $config->required_heroes,
            ]);
        }

        if ($defaultId !== null) {
            DB::table('game_modes')->where('id', $defaultId)->update(['is_default' => true]);
        }

        Schema::dropIfExists('deck_attributes_configurations');
    }

    /** "Clásico"/"Estándar" por nombre es (case-insensitive) o el id más bajo. */
    protected function pickDefaultModeId(): ?int
    {
        $modes = DB::table('game_modes')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'name']);

        foreach ($modes as $mode) {
            $name = json_decode((string) $mode->name, true)['es'] ?? '';
            if (in_array(mb_strtolower(trim($name)), ['clásico', 'clasico', 'estándar', 'estandar'], true)) {
                return (int) $mode->id;
            }
        }

        return $modes->first()?->id !== null ? (int) $modes->first()->id : null;
    }

    public function down(): void
    {
        // Best effort: recrea la tabla vieja con una configuración por modo.
        Schema::create('deck_attributes_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_mode_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('min_cards')->default(30);
            $table->integer('max_cards')->default(40);
            $table->integer('max_copies_per_card')->default(2);
            $table->unsignedTinyInteger('required_heroes')->default(0);
            $table->datetimes();
        });

        foreach (DB::table('game_modes')->get() as $mode) {
            DB::table('deck_attributes_configurations')->insert([
                'game_mode_id' => $mode->id,
                'min_cards' => $mode->min_cards,
                'max_cards' => $mode->max_cards,
                'max_copies_per_card' => $mode->max_copies_per_card,
                'required_heroes' => $mode->required_heroes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('game_modes', function (Blueprint $table) {
            $table->dropColumn(['min_cards', 'max_cards', 'max_copies_per_card', 'required_heroes', 'is_default']);
        });
    }
};
