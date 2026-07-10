<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class CardTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'hero_superclass_id' => $this->hero_superclass_id,
            // Inline (id + nombre) para no depender del Resource de otro cluster
            'hero_superclass' => $this->whenLoaded('heroSuperclass', fn () => $this->heroSuperclass ? [
                'id' => $this->heroSuperclass->id,
                'name' => $this->heroSuperclass->getTranslations('name'),
            ] : null),
            'allows_subtypes' => $this->allows_subtypes,
            'is_equipment' => $this->is_equipment,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
