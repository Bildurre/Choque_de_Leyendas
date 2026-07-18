<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class FactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'slug' => $this->getTranslations('slug'),
            'lore_text' => $this->getTranslations('lore_text'),
            'epic_quote' => $this->getTranslations('epic_quote'),
            'color' => $this->color,
            'text_is_dark' => $this->text_is_dark,
            'image' => $this->imageUrl(),
            'previews' => $this->previewUrls(),
            'is_published' => $this->is_published,
            // Cantidades (withCount): el panel derecho del listado las pinta.
            'heroes_count' => $this->whenCounted('heroes'),
            'cards_count' => $this->whenCounted('cards'),
            'faction_decks_count' => $this->whenCounted('factionDecks'),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
