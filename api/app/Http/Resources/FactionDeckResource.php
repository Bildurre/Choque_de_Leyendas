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
            // El modo lleva su configuración de mazos: el editor la usa para
            // los límites en vivo sin otra petición.
            'game_mode' => $this->whenLoaded('gameMode', fn () => [
                'id' => $this->gameMode->id,
                'name' => $this->gameMode->getTranslations('name'),
                'min_cards' => $this->gameMode->min_cards,
                'max_cards' => $this->gameMode->max_cards,
                'max_copies_per_card' => $this->gameMode->max_copies_per_card,
                'required_heroes' => $this->gameMode->required_heroes,
                'is_default' => $this->gameMode->is_default,
            ]),
            'factions' => $this->whenLoaded('factions', fn () => $this->factions->map(
                fn (Faction $faction) => [
                    'id' => $faction->id,
                    'name' => $faction->getTranslations('name'),
                    'color' => $faction->color,
                ],
            )),
            // Héroes y cartas con las copias del pivot y su facción (el
            // editor de mazos acota por facciones y avisa de las quitadas)
            'heroes' => $this->whenLoaded('heroes', fn () => $this->heroes->map(
                fn (Hero $hero) => [
                    'id' => $hero->id,
                    'name' => $hero->getTranslations('name'),
                    'image' => $hero->imageUrl(),
                    'faction_id' => $hero->faction_id,
                    'copies' => (int) $hero->pivot->copies,
                ],
            )),
            'cards' => $this->whenLoaded('cards', fn () => $this->cards->map(
                fn (Card $card) => [
                    'id' => $card->id,
                    'name' => $card->getTranslations('name'),
                    'cost' => $card->cost,
                    'image' => $card->imageUrl(),
                    'faction_id' => $card->faction_id,
                    'copies' => (int) $card->pivot->copies,
                ],
            )),
            'image' => $this->imageUrl(),
            'previews' => $this->previewUrls(),
            'total_cards' => $this->total_cards,
            'total_heroes' => $this->total_heroes,
            'is_published' => $this->is_published,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
