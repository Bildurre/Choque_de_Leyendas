<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class EquipmentTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'uses_hands' => (bool) $this->uses_hands,
            // Cuántos subtipos cuelgan del tipo (withCount del index)
            'subtypes_count' => $this->whenCounted('subtypes'),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
