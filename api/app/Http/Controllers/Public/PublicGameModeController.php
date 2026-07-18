<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\GameMode;

/**
 * Web pública: el modo de juego por defecto con su configuración de mazos.
 * Lo consume el contador de vidas (nº de héroes habitual) sin auth;
 * localizado por SetLocale.
 */
class PublicGameModeController extends Controller
{
    public function default()
    {
        $mode = GameMode::defaultMode();

        return response()->json([
            'data' => $mode ? [
                'id' => $mode->id,
                'name' => $mode->getTranslation('name', app()->getLocale()),
                'min_cards' => $mode->min_cards,
                'max_cards' => $mode->max_cards,
                'max_copies_per_card' => $mode->max_copies_per_card,
                'required_heroes' => $mode->required_heroes,
            ] : null,
        ]);
    }
}
