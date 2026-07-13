<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El tipo de equipo deja de tener categoría cerrada (weapon|armor) y pasa a
 * un flag de comportamiento: uses_hands (las armas llevan manos). Los datos
 * existentes se conservan: cada tipo con category=weapon pasa a uses_hands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_types', function (Blueprint $table) {
            // Flag de comportamiento (sustituye a la categoría del viejo):
            // las cartas de un tipo con uses_hands exigen manos (armas).
            $table->boolean('uses_hands')->default(false);
        });

        DB::table('equipment_types')->where('category', 'weapon')->update(['uses_hands' => true]);

        Schema::table('equipment_types', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_types', function (Blueprint $table) {
            $table->string('category', 20)->default('armor');
        });

        DB::table('equipment_types')->where('uses_hands', true)->update(['category' => 'weapon']);

        Schema::table('equipment_types', function (Blueprint $table) {
            $table->dropColumn('uses_hands');
        });
    }
};
