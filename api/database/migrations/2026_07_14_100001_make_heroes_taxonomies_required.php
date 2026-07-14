<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Todo héroe tiene facción, raza y clase: las tres FK pasan a NOT NULL y el
 * borrado de la taxonomía se restringe (antes ponía null). Si hay héroes con
 * alguna de las tres vacía la migración ABORTA con un mensaje claro: hay que
 * completarlos a mano (o borrarlos) antes de repetir `php artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('heroes')
            ->whereNull('faction_id')
            ->orWhereNull('hero_race_id')
            ->orWhereNull('hero_class_id')
            ->count();
        if ($orphans > 0) {
            throw new RuntimeException(
                "Hay {$orphans} héroe(s) sin facción, raza o clase: complétalos (o bórralos) y vuelve a lanzar la migración."
            );
        }

        Schema::table('heroes', function (Blueprint $table) {
            $table->dropForeign(['faction_id']);
            $table->dropForeign(['hero_race_id']);
            $table->dropForeign(['hero_class_id']);
        });

        Schema::table('heroes', function (Blueprint $table) {
            $table->foreignId('faction_id')->nullable(false)->change();
            $table->foreignId('hero_race_id')->nullable(false)->change();
            $table->foreignId('hero_class_id')->nullable(false)->change();
            $table->foreign('faction_id')->references('id')->on('factions')->restrictOnDelete();
            $table->foreign('hero_race_id')->references('id')->on('hero_races')->restrictOnDelete();
            $table->foreign('hero_class_id')->references('id')->on('hero_classes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('heroes', function (Blueprint $table) {
            $table->dropForeign(['faction_id']);
            $table->dropForeign(['hero_race_id']);
            $table->dropForeign(['hero_class_id']);
        });

        Schema::table('heroes', function (Blueprint $table) {
            $table->foreignId('faction_id')->nullable()->change();
            $table->foreignId('hero_race_id')->nullable()->change();
            $table->foreignId('hero_class_id')->nullable()->change();
            $table->foreign('faction_id')->references('id')->on('factions')->nullOnDelete();
            $table->foreign('hero_race_id')->references('id')->on('hero_races')->nullOnDelete();
            $table->foreign('hero_class_id')->references('id')->on('hero_classes')->nullOnDelete();
        });
    }
};
