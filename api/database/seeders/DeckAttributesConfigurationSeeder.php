<?php

namespace Database\Seeders;

use App\Models\DeckAttributesConfiguration;
use App\Models\GameMode;
use Illuminate\Database\Seeder;

/**
 * Configuración de mazo del modo estándar (el viejo la insertaba en la
 * migración con game_mode_id = 1; aquí se busca el modo por nombre).
 */
class DeckAttributesConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotente: si ya hay configuraciones, no duplica.
        if (DeckAttributesConfiguration::exists()) {
            return;
        }

        // El modo estándar del GameModeSeeder; si no está, el primero que haya.
        $mode = GameMode::where('name->es', 'Estándar')
            ->orWhere('name->en', 'Standard')
            ->first() ?? GameMode::orderBy('id')->first();

        DeckAttributesConfiguration::create([
            'game_mode_id' => $mode?->id,
            'min_cards' => 30,
            'max_cards' => 40,
            'max_copies_per_card' => 2,
            'required_heroes' => 5,
        ]);
    }
}
