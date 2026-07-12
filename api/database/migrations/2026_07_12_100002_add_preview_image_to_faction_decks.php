<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faction_decks', function (Blueprint $table) {
            // Mapa clave-de-preview => (locale => ruta del PNG); lo gestiona
            // el trait HasPreviewImage del motor (ver doc 01).
            $table->json('preview_image')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('faction_decks', function (Blueprint $table) {
            $table->dropColumn('preview_image');
        });
    }
};
