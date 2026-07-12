<?php

namespace App\Http\Resources\Public;

use App\Support\PublicCatalogItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha pública de facción: lore + cita y sus listas de contenido publicado
 * (héroes/cartas en formato ítem de catálogo, mazos en formato de índice).
 * name/slug/description van POR LOCALES (los pide EntityDetailView para la
 * canónica y el hreflang); el resto localizado al locale de la petición.
 */
class PublicFactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'slug' => $this->getTranslations('slug'),
            'description' => $this->getTranslations('lore_text'),
            'color' => $this->color,
            'text_is_dark' => (bool) $this->text_is_dark,
            'icon' => $this->imageUrl(),
            // Fondo de página: el icono de la facción (patrón del viejo)
            'image' => $this->imageUrl(),
            'lore_text' => $this->getTranslation('lore_text', $locale),
            'epic_quote' => $this->getTranslation('epic_quote', $locale),
            'heroes_count' => (int) ($this->heroes_count ?? $this->heroes->count()),
            'cards_count' => (int) ($this->cards_count ?? $this->cards->count()),
            'decks_count' => (int) ($this->decks_count ?? $this->factionDecks->count()),
            'heroes' => $this->heroes
                ->map(fn ($hero) => PublicCatalogItem::fromModel($hero, 'hero', $locale))
                ->values(),
            'cards' => $this->cards
                ->map(fn ($card) => PublicCatalogItem::fromModel($card, 'card', $locale))
                ->values(),
            'decks' => PublicFactionDeckItemResource::collection($this->whenLoaded('factionDecks')),
        ];
    }
}
