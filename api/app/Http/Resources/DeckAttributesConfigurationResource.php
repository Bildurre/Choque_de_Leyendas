<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin. Sin soft delete: deleted_at siempre null. */
class DeckAttributesConfigurationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_mode_id' => $this->game_mode_id,
            // Modo en mínimo (id + nombre): su Resource es de otro cluster.
            'game_mode' => $this->whenLoaded('gameMode', fn () => [
                'id' => $this->gameMode->id,
                'name' => $this->gameMode->getTranslations('name'),
            ]),
            'min_cards' => $this->min_cards,
            'max_cards' => $this->max_cards,
            'max_copies_per_card' => $this->max_copies_per_card,
            'required_heroes' => $this->required_heroes,
            'deleted_at' => null,
        ];
    }
}
