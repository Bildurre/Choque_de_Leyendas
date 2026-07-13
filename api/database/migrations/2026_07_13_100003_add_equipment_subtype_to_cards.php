<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las cartas de equipo guardan también el subtipo (el tipado completo es
 * "Equipo - tipo - subtipo"). Nullable: las cartas existentes no tienen
 * subtipo todavía (se asigna al editarlas; el form lo exige para equipo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->foreignId('equipment_subtype_id')
                ->nullable()
                ->after('equipment_type_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_subtype_id');
        });
    }
};
