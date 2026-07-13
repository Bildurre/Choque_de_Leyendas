<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class EquipmentSubtypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'equipment_type_id' => $this->equipment_type_id,
            'equipment_type' => $this->whenLoaded('equipmentType', fn () => [
                'id' => $this->equipmentType->id,
                'name' => $this->equipmentType->getTranslations('name'),
                'uses_hands' => (bool) $this->equipmentType->uses_hands,
            ]),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
