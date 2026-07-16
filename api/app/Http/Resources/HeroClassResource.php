<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class HeroClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'name_female' => $this->getTranslations('name_female'),
            'passive' => $this->getTranslations('passive'),
            'hero_superclass_id' => $this->hero_superclass_id,
            'hero_superclass' => new HeroSuperclassResource($this->whenLoaded('heroSuperclass')),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
