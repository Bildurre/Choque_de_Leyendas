<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre femenino OPCIONAL (traducible) en las taxonomías de héroe: se usa
 * SOLO en contexto de un héroe de género femenino (HasGenderedName); los
 * listados/filtros siguen con `name`. Alter aparte de las create: corre
 * sobre BBDD con datos reales sin tocarlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['hero_classes', 'hero_superclasses', 'hero_races'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->json('name_female')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        foreach (['hero_classes', 'hero_superclasses', 'hero_races'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('name_female');
            });
        }
    }
};
