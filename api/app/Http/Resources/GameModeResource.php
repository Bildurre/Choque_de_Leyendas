<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class GameModeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'description' => $this->getTranslations('description'),
            // Configuración de mazos del modo (fusionada; antes tabla aparte)
            'min_cards' => $this->min_cards,
            'max_cards' => $this->max_cards,
            'max_copies_per_card' => $this->max_copies_per_card,
            'required_heroes' => $this->required_heroes,
            'is_default' => $this->is_default,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
