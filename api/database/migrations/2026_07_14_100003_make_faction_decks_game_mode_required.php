<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Todo mazo de facción juega a un modo: game_mode_id pasa a NOT NULL y el
 * borrado del modo se restringe (antes ponía null). Si hay mazos sin modo la
 * migración ABORTA con un mensaje claro: hay que asignárselo a mano (o
 * borrarlos) antes de repetir `php artisan migrate`. Las facciones del mazo
 * (mínimo una) viven en un pivot: se exigen en la validación, no aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('faction_decks')->whereNull('game_mode_id')->count();
        if ($orphans > 0) {
            throw new RuntimeException(
                "Hay {$orphans} mazo(s) sin modo de juego: asígnales uno (o bórralos) y vuelve a lanzar la migración."
            );
        }

        Schema::table('faction_decks', function (Blueprint $table) {
            $table->dropForeign(['game_mode_id']);
        });

        Schema::table('faction_decks', function (Blueprint $table) {
            $table->foreignId('game_mode_id')->nullable(false)->change();
            $table->foreign('game_mode_id')->references('id')->on('game_modes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('faction_decks', function (Blueprint $table) {
            $table->dropForeign(['game_mode_id']);
        });

        Schema::table('faction_decks', function (Blueprint $table) {
            $table->foreignId('game_mode_id')->nullable()->change();
            $table->foreign('game_mode_id')->references('id')->on('game_modes')->nullOnDelete();
        });
    }
};
