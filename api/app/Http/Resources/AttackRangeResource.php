<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class AttackRangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            // Cuántas cartas / habilidades llevan el rango (withCount del index)
            'cards_count' => $this->whenCounted('cards'),
            'hero_abilities_count' => $this->whenCounted('heroAbilities'),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
