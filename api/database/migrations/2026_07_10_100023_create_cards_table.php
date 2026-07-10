<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->json('lore_text')->nullable();
            $table->json('epic_quote')->nullable();
            $table->json('effect')->nullable();
            $table->json('restriction')->nullable();
            $table->foreignId('faction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('card_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('card_subtype_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('equipment_type_id')->nullable()->constrained()->nullOnDelete();
            // Enum del viejo como string + validación in:physical,magical
            $table->string('attack_type')->nullable();
            $table->foreignId('attack_range_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attack_subtype_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hero_ability_id')->nullable()->constrained()->nullOnDelete();
            // 1 o 2 manos (solo equipo/armas)
            $table->tinyInteger('hands')->nullable();
            // Coste en dados, letras R/G/B normalizadas (HasCost)
            $table->string('cost', 5)->nullable();
            $table->boolean('area')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_published')->default(false);
            // Previews PNG por clave y locale (HasPreviewImage)
            $table->json('preview_image')->nullable();
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
