<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_types', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            // Superclase asociada (única por tipo; p. ej. Técnica→Guerrero)
            $table->foreignId('hero_superclass_id')->nullable()->unique()->constrained()->nullOnDelete();
            // Flags de comportamiento: sustituyen a los ids mágicos del viejo
            // (technique/spell/litany). allows_subtypes = muestra el select de
            // subtipo en cartas; is_equipment = tipo de equipo y manos.
            $table->boolean('allows_subtypes')->default(false);
            $table->boolean('is_equipment')->default(false);
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_types');
    }
};
