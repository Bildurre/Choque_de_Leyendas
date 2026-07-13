<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha pública de carta (portada del card-detail del viejo): coste parseado,
 * tipo con sus flags, subtipo/equipo SEGÚN esos flags, ataque, efectos y
 * habilidad otorgada. name/slug/description por locales (EntityDetailView);
 * el resto localizado al locale de la petición.
 */
class PublicCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $type = $this->cardType;

        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'slug' => $this->getTranslations('slug'),
            'description' => $this->getTranslations('lore_text'),
            'image' => $this->imageUrl(),
            'preview' => $this->previewUrl($locale, 'card'),
            'faction' => PublicFactionItemResource::ref($this->faction, $locale),
            'type' => $type ? [
                'name' => $type->getTranslation('name', $locale),
                'superclass' => $type->heroSuperclass?->getTranslation('name', $locale),
                'allows_subtypes' => (bool) $type->allows_subtypes,
                'is_equipment' => (bool) $type->is_equipment,
            ] : null,
            // Subtipo y equipo solo aplican según los flags del tipo
            'subtype' => $type?->allows_subtypes
                ? $this->cardSubtype?->getTranslation('name', $locale)
                : null,
            'equipment' => $type?->is_equipment ? [
                'type' => $this->equipmentType?->getTranslation('name', $locale),
                'subtype' => $this->equipmentSubtype?->getTranslation('name', $locale),
                'hands' => $this->hands,
            ] : null,
            'cost' => $this->cost,
            'cost_parsed' => $this->parsed_cost,
            'is_unique' => (bool) $this->is_unique,
            'attack' => [
                // La clave (physical|magical) se localiza en el front
                'type' => $this->attack_type,
                'range' => $this->attackRange?->getTranslation('name', $locale),
                'subtype' => $this->attackSubtype?->getTranslation('name', $locale),
                'area' => (bool) $this->area,
            ],
            'effect' => $this->getTranslation('effect', $locale),
            'restriction' => $this->getTranslation('restriction', $locale),
            'granted_ability' => $this->heroAbility
                ? (new PublicHeroAbilityResource($this->heroAbility))->toArray($request)
                : null,
            'lore_text' => $this->getTranslation('lore_text', $locale),
            'epic_quote' => $this->getTranslation('epic_quote', $locale),
        ];
    }
}
