<?php

namespace App\Http\Resources\Public;

use App\Models\Faction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ítem del índice público de facciones: lo que necesita la tarjeta CSS del
 * front (color + text_is_dark + icono) más los contadores de publicados.
 * Localizado al locale de la petición (SetLocale), sin mapas completos.
 */
class PublicFactionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', $locale),
            'slug' => $this->getTranslation('slug', $locale),
            'color' => $this->color,
            'text_is_dark' => (bool) $this->text_is_dark,
            'icon' => $this->imageUrl(),
            'heroes_count' => (int) ($this->heroes_count ?? 0),
            'cards_count' => (int) ($this->cards_count ?? 0),
        ];
    }

    /**
     * Referencia mínima de facción para otras fichas (cartas, héroes, mazos):
     * nombre + color para el marco. Si la facción no está publicada el slug
     * va a null (se pinta sin enlace: nada de links muertos a 404).
     */
    public static function ref(?Faction $faction, string $locale): ?array
    {
        if (! $faction) {
            return null;
        }

        return [
            'id' => $faction->id,
            'name' => $faction->getTranslation('name', $locale),
            'slug' => $faction->is_published
                ? ($faction->getTranslation('slug', $locale) ?: null)
                : null,
            'color' => $faction->color,
            'text_is_dark' => (bool) $faction->text_is_dark,
        ];
    }
}
