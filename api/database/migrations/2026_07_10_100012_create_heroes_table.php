<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heroes', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->json('lore_text')->nullable();
            $table->json('epic_quote')->nullable();
            $table->json('passive_name')->nullable();
            $table->json('passive_description')->nullable();
            // Obligatorias; el borrado de la taxonomía se restringe si tiene héroes
            $table->foreignId('faction_id')->constrained()->restrictOnDelete();
            $table->foreignId('hero_race_id')->constrained()->restrictOnDelete();
            $table->foreignId('hero_class_id')->constrained()->restrictOnDelete();
            // Enum del viejo como string + validación in:male,female
            $table->string('gender')->default('male');
            $table->integer('agility')->default(2);
            $table->integer('mental')->default(2);
            $table->integer('will')->default(2);
            $table->integer('strength')->default(2);
            $table->integer('armor')->default(2);
            $table->boolean('is_published')->default(false);
            // Previews PNG por clave y locale (HasPreviewImage)
            $table->json('preview_image')->nullable();
            $table->datetimes();
            $table->softDeletesDatetime();
        });

        // Pivot héroe <-> habilidad activa, con orden (position 1-based)
        Schema::create('hero_hero_ability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hero_ability_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->datetimes();

            $table->unique(['hero_id', 'hero_ability_id'], 'hero_ability_unique');
            $table->index(['hero_id', 'position'], 'hero_ability_position_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_hero_ability');
        Schema::dropIfExists('heroes');
    }
};
