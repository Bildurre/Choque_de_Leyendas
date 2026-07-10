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
            'deleted_at' => $this->deleted_at,
        ];
    }
}
