<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class GameModeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'description' => $this->getTranslations('description'),
            // Configuración de mazos del modo (fusionada; antes tabla aparte)
            'min_cards' => $this->min_cards,
            'max_cards' => $this->max_cards,
            'max_copies_per_card' => $this->max_copies_per_card,
            'required_heroes' => $this->required_heroes,
            'is_default' => $this->is_default,
            // Cuántos mazos usan el modo (withCount del index)
            'faction_decks_count' => $this->whenCounted('factionDecks'),
            // Los mazos en mínimo (id + nombre + slug): el panel los lista
            // enlazados a su single por el slug del locale activo.
            'faction_decks' => $this->whenLoaded('factionDecks', fn () => $this->factionDecks->map(
                fn ($deck) => [
                    'id' => $deck->id,
                    'name' => $deck->getTranslations('name'),
                    'slug' => $deck->getTranslations('slug'),
                ],
            )),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
