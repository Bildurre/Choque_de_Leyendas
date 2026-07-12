<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Habilidad de héroe para la web pública: texto localizado, coste parseado
 * dado a dado y datos de ataque. La usan la ficha de héroe (activas) y la de
 * carta (habilidad otorgada).
 */
class PublicHeroAbilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', $locale),
            'description' => $this->getTranslation('description', $locale),
            'cost' => $this->cost,
            'cost_parsed' => $this->parsed_cost,
            'attack' => [
                // La clave (physical|magical) se localiza en el front
                'type' => $this->attack_type,
                'range' => $this->attackRange?->getTranslation('name', $locale),
                'subtype' => $this->attackSubtype?->getTranslation('name', $locale),
            ],
            'area' => (bool) $this->area,
        ];
    }
}
