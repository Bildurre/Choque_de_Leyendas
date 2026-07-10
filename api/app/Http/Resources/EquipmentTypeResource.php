<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación para el admin: todas las traducciones, para editar. La
 * categoría viaja cruda (weapon|armor); su etiqueta la pone el i18n del admin.
 */
class EquipmentTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'category' => $this->category,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
