<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Toda clase de héroe pertenece a una superclase (en el viejo ya era
 * obligatoria en el formulario): hero_superclass_id pasa a NOT NULL y el
 * borrado de la superclase se restringe (antes ponía null). Si hay clases sin
 * superclase la migración ABORTA con un mensaje claro: hay que asignársela a
 * mano (o borrarlas) antes de repetir `php artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('hero_classes')->whereNull('hero_superclass_id')->count();
        if ($orphans > 0) {
            throw new RuntimeException(
                "Hay {$orphans} clase(s) de héroe sin superclase: asígnales una (o bórralas) y vuelve a lanzar la migración."
            );
        }

        Schema::table('hero_classes', function (Blueprint $table) {
            $table->dropForeign(['hero_superclass_id']);
        });

        Schema::table('hero_classes', function (Blueprint $table) {
            $table->foreignId('hero_superclass_id')->nullable(false)->change();
            $table->foreign('hero_superclass_id')->references('id')->on('hero_superclasses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hero_classes', function (Blueprint $table) {
            $table->dropForeign(['hero_superclass_id']);
        });

        Schema::table('hero_classes', function (Blueprint $table) {
            $table->foreignId('hero_superclass_id')->nullable()->change();
            $table->foreign('hero_superclass_id')->references('id')->on('hero_superclasses')->nullOnDelete();
        });
    }
};
