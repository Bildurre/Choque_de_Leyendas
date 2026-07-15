<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Histórico del contador de vidas (herramienta pública): cada fila es
        // una partida física de un usuario registrado. El estado completo
        // (equipos: facciones + héroes con sus vidas) viaja como json opaco
        // del cliente; el servidor solo lo guarda y lo devuelve.
        Schema::create('life_counter_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('state');
            $table->string('status')->default('active'); // active|finished
            $table->datetimes();

            // El listado y el "retomar la activa" filtran por dueño + estado.
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('life_counter_matches');
    }
};
