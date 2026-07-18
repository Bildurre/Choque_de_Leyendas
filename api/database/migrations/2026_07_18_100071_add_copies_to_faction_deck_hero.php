<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El constructor de mazos permite varias copias del mismo héroe (igual que
 * las cartas): el pivot mazo↔héroe gana `copies` (los existentes quedan a 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faction_deck_hero', function (Blueprint $table) {
            $table->integer('copies')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('faction_deck_hero', function (Blueprint $table) {
            $table->dropColumn('copies');
        });
    }
};
