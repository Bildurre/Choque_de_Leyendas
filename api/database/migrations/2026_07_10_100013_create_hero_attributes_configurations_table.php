<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singleton de configuración (una sola fila, sin soft delete):
        // límites de atributos y fórmula de vida de los héroes.
        Schema::create('hero_attributes_configurations', function (Blueprint $table) {
            $table->id();
            $table->integer('min_attribute_value')->default(1);
            $table->integer('max_attribute_value')->default(5);
            $table->integer('min_total_attributes')->default(12);
            $table->integer('max_total_attributes')->default(18);
            $table->integer('agility_multiplier')->default(-1);
            $table->integer('mental_multiplier')->default(-1);
            $table->integer('will_multiplier')->default(1);
            $table->integer('strength_multiplier')->default(-1);
            $table->integer('armor_multiplier')->default(1);
            $table->integer('total_health_base')->default(25);
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_attributes_configurations');
    }
};
