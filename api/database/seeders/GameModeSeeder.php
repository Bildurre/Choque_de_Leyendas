<?php

namespace Database\Seeders;

use App\Models\GameMode;
use Illuminate\Database\Seeder;

/**
 * Modo de juego base "Estándar" con su configuración de mazos integrada
 * (30–40 cartas, 2 copias, 5 héroes: los valores del viejo) y marcado como
 * por defecto. Garantiza además el invariante "exactamente un por defecto"
 * si ya había modos sin ninguno marcado.
 */
class GameModeSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotente: si ya hay modos (aunque estén en papelera), no duplica;
        // solo asegura que alguno sea el por defecto.
        if (GameMode::withTrashed()->exists()) {
            if (! GameMode::where('is_default', true)->exists()) {
                GameMode::orderBy('id')->first()?->update(['is_default' => true]);
            }

            return;
        }

        GameMode::create([
            'name' => ['es' => 'Estándar', 'eu' => 'Estandarra', 'en' => 'Standard'],
            'description' => [
                'es' => 'Modo de juego estándar',
                'eu' => 'Joko modu estandarra',
                'en' => 'Standard game mode',
            ],
            'min_cards' => 30,
            'max_cards' => 40,
            'max_copies_per_card' => 2,
            'required_heroes' => 5,
            'is_default' => true,
        ]);
    }
}
