<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_classes', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            // Habilidad pasiva de la clase (HTML del editor, por locale)
            $table->json('passive')->nullable();
            $table->foreignId('hero_superclass_id')->nullable()->constrained()->nullOnDelete();
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_classes');
    }
};
