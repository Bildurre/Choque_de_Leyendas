<?php

namespace Database\Seeders;

use App\Models\GameMode;
use Illuminate\Database\Seeder;

/** Modo de juego base "Estándar" (el viejo lo insertaba en la migración). */
class GameModeSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotente: si ya hay modos (aunque estén en papelera), no duplica.
        if (GameMode::withTrashed()->exists()) {
            return;
        }

        GameMode::create([
            'name' => ['es' => 'Estándar', 'eu' => 'Estandarra', 'en' => 'Standard'],
            'description' => [
                'es' => 'Modo de juego estándar',
                'eu' => 'Joko modu estandarra',
                'en' => 'Standard game mode',
            ],
        ]);
    }
}
