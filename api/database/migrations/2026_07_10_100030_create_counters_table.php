<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counters', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('effect')->nullable();
            // 'boon'|'bane' (validación in:... en el controller, sin enum SQL)
            $table->string('type', 10);
            $table->boolean('is_published')->default(false);
            // Previews PNG por clave y locale (HasPreviewImage)
            $table->json('preview_image')->nullable();
            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counters');
    }
};
