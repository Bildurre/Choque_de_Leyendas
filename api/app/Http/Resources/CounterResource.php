<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class CounterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'effect' => $this->getTranslations('effect'),
            'type' => $this->type,
            'image' => $this->imageUrl(),
            'previews' => $this->previewUrls(),
            'is_published' => $this->is_published,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
