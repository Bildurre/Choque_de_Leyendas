<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ítem del índice público de mazos: lo que necesita la tarjeta CSS del front
 * (icono, modo de juego para las pestañas, facciones con color para el badge
 * o el gradiente multifacción) más los totales de contenido publicado.
 */
class PublicFactionDeckItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', $locale),
            'slug' => $this->getTranslation('slug', $locale),
            'icon' => $this->imageUrl(),
            'game_mode' => $this->gameMode ? [
                'id' => $this->gameMode->id,
                'name' => $this->gameMode->getTranslation('name', $locale),
            ] : null,
            'factions' => $this->factions
                ->map(fn ($faction) => PublicFactionItemResource::ref($faction, $locale))
                ->values(),
            'total_heroes' => (int) $this->total_heroes,
            'total_cards' => (int) $this->total_cards,
        ];
    }
}
