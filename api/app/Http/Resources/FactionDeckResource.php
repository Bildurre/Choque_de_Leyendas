<?php

namespace App\Http\Resources;

use App\Models\Card;
use App\Models\Faction;
use App\Models\Hero;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class FactionDeckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'slug' => $this->getTranslations('slug'),
            'description' => $this->getTranslations('description'),
            'epic_quote' => $this->getTranslations('epic_quote'),
            'game_mode_id' => $this->game_mode_id,
            // Relaciones en mínimo: sus Resources completos son de otros clusters.
            'game_mode' => $this->whenLoaded('gameMode', fn () => [
                'id' => $this->gameMode->id,
                'name' => $this->gameMode->getTranslations('name'),
            ]),
            'factions' => $this->whenLoaded('factions', fn () => $this->factions->map(
                fn (Faction $faction) => [
                    'id' => $faction->id,
                    'name' => $faction->getTranslations('name'),
                    'color' => $faction->color,
                ],
            )),
            'heroes' => $this->whenLoaded('heroes', fn () => $this->heroes->map(
                fn (Hero $hero) => [
                    'id' => $hero->id,
                    'name' => $hero->getTranslations('name'),
                    'image' => $hero->imageUrl(),
                ],
            )),
            // Cartas con las copias del pivot (editor de mazos)
            'cards' => $this->whenLoaded('cards', fn () => $this->cards->map(
                fn (Card $card) => [
                    'id' => $card->id,
                    'name' => $card->getTranslations('name'),
                    'cost' => $card->cost,
                    'image' => $card->imageUrl(),
                    'copies' => (int) $card->pivot->copies,
                ],
            )),
            'image' => $this->imageUrl(),
            'total_cards' => $this->total_cards,
            'total_heroes' => $this->total_heroes,
            'is_published' => $this->is_published,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
