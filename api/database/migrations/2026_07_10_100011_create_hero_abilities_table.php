<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_abilities', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            // Enum del viejo como string + validación in:physical,magical
            $table->string('attack_type')->nullable();
            $table->foreignId('attack_range_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attack_subtype_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('area')->default(false);
            // Coste en dados R/G/B normalizado por HasCost (p. ej. "RRG")
            $table->string('cost', 5);
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_abilities');
    }
};
