<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Toda carta tiene facción: faction_id pasa a NOT NULL y el borrado de la
 * facción se restringe (antes ponía null). Si hay cartas sin facción la
 * migración ABORTA con un mensaje claro: hay que asignarles facción a mano
 * (o borrarlas) antes de repetir `php artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('cards')->whereNull('faction_id')->count();
        if ($orphans > 0) {
            throw new RuntimeException(
                "Hay {$orphans} carta(s) sin facción: asígnales una (o bórralas) y vuelve a lanzar la migración."
            );
        }

        Schema::table('cards', function (Blueprint $table) {
            $table->dropForeign(['faction_id']);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->foreignId('faction_id')->nullable(false)->change();
            $table->foreign('faction_id')->references('id')->on('factions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropForeign(['faction_id']);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->foreignId('faction_id')->nullable()->change();
            $table->foreign('faction_id')->references('id')->on('factions')->nullOnDelete();
        });
    }
};
