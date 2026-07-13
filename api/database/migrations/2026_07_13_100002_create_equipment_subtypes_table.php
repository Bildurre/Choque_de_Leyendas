<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subtipos de equipo: todo equipo tiene subtipo (Espada, Yelmo…) y cada
 * subtipo pertenece a un tipo (Arma, Armadura…). Taxonomía sin slug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_subtypes', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            // Todo subtipo pertenece a un tipo de equipo (obligatorio).
            $table->foreignId('equipment_type_id')->constrained()->restrictOnDelete();
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_subtypes');
    }
};
