<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class HeroAbilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'description' => $this->getTranslations('description'),
            'attack_type' => $this->attack_type,
            'attack_range_id' => $this->attack_range_id,
            'attack_subtype_id' => $this->attack_subtype_id,
            'attack_range' => new AttackRangeResource($this->whenLoaded('attackRange')),
            'attack_subtype' => new AttackSubtypeResource($this->whenLoaded('attackSubtype')),
            'area' => (bool) $this->area,
            'cost' => $this->cost,
            // Cuántos héroes la tienen / cartas la otorgan (withCount del index)
            'heroes_count' => $this->whenCounted('heroes'),
            'cards_count' => $this->whenCounted('cards'),
            // Héroes y cartas en mínimo (id + nombre + slug): el panel los
            // lista enlazados a su single por el slug del locale activo.
            'heroes' => $this->whenLoaded('heroes', fn () => $this->heroes->map(
                fn ($hero) => [
                    'id' => $hero->id,
                    'name' => $hero->getTranslations('name'),
                    'slug' => $hero->getTranslations('slug'),
                ],
            )),
            'cards' => $this->whenLoaded('cards', fn () => $this->cards->map(
                fn ($card) => [
                    'id' => $card->id,
                    'name' => $card->getTranslations('name'),
                    'slug' => $card->getTranslations('slug'),
                ],
            )),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
