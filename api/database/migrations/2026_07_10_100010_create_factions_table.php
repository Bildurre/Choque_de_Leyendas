<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factions', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->json('lore_text')->nullable();
            $table->json('epic_quote')->nullable();
            $table->string('color', 7);
            // Se calcula al guardar por luminancia YIQ del color (no se edita)
            $table->boolean('text_is_dark')->default(false);
            $table->boolean('is_published')->default(false);
            // Previews PNG por clave y locale (HasPreviewImage)
            $table->json('preview_image')->nullable();
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factions');
    }
};
