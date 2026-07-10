<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Límites de construcción de mazos por modo de juego (sin soft delete)
        Schema::create('deck_attributes_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_mode_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('min_cards')->default(30);
            $table->integer('max_cards')->default(40);
            $table->integer('max_copies_per_card')->default(2);
            $table->unsignedTinyInteger('required_heroes')->default(0);
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deck_attributes_configurations');
    }
};
