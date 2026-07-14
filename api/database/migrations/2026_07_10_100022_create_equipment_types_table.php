<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_types', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            // Flag de comportamiento: las cartas de un tipo con uses_hands
            // exigen manos (armas); el resto son armaduras/accesorios.
            $table->boolean('uses_hands')->default(false);
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_types');
    }
};
