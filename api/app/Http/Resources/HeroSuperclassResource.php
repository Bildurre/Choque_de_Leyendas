<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class HeroSuperclassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'name_female' => $this->getTranslations('name_female'),
            // Cuántas clases cuelgan de la superclase (withCount del index)
            'hero_classes_count' => $this->whenCounted('heroClasses'),
            // Tipo de carta asociado (único por superclase, nullable).
            // Inline (id + nombre) para no arrastrar el Resource completo.
            'card_type' => $this->whenLoaded('cardType', fn () => $this->cardType ? [
                'id' => $this->cardType->id,
                'name' => $this->cardType->getTranslations('name'),
            ] : null),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
