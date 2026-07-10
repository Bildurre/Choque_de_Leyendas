<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faction_decks', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->json('description')->nullable();
            $table->json('epic_quote')->nullable();
            $table->foreignId('game_mode_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_published')->default(false);
            $table->datetimes();
            $table->softDeletesDatetime();
        });

        // Pivot mazo <-> carta, con nº de copias
        Schema::create('card_faction_deck', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faction_deck_id')->constrained()->cascadeOnDelete();
            $table->integer('copies')->default(1);
            $table->datetimes();

            $table->unique(['card_id', 'faction_deck_id'], 'card_faction_deck_unique');
        });

        // Pivot mazo <-> héroe (sin copias: un héroe por mazo)
        Schema::create('faction_deck_hero', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faction_deck_id')->constrained()->cascadeOnDelete();
            $table->datetimes();

            $table->unique(['hero_id', 'faction_deck_id'], 'faction_deck_hero_unique');
        });

        // Pivot mazo <-> facción (mazos multifacción)
        Schema::create('faction_deck_faction', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faction_deck_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faction_id')->constrained()->cascadeOnDelete();
            $table->datetimes();

            $table->unique(['faction_deck_id', 'faction_id'], 'faction_deck_faction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faction_deck_faction');
        Schema::dropIfExists('faction_deck_hero');
        Schema::dropIfExists('card_faction_deck');
        Schema::dropIfExists('faction_decks');
    }
};
