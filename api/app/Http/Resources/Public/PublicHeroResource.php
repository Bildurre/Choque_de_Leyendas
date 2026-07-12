<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha pública de héroe (portada del hero-detail del viejo): atributos +
 * salud derivada, pasivas (de clase y propia), habilidades activas con coste
 * parseado, facción con color y preview PNG grande. name/slug/description por
 * locales (EntityDetailView); el resto localizado a la petición.
 */
class PublicHeroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        $classPassive = $this->heroClass?->getTranslation('passive', $locale);
        $passiveName = $this->getTranslation('passive_name', $locale);
        $passiveDescription = $this->getTranslation('passive_description', $locale);

        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'slug' => $this->getTranslations('slug'),
            'description' => $this->getTranslations('lore_text'),
            'image' => $this->imageUrl(),
            'preview' => $this->previewUrl($locale, 'hero'),
            'faction' => PublicFactionItemResource::ref($this->faction, $locale),
            'race' => $this->heroRace?->getTranslation('name', $locale),
            // La clave (male|female) se localiza en el front
            'gender' => $this->gender,
            'class' => $this->heroClass?->getTranslation('name', $locale),
            'superclass' => $this->heroClass?->heroSuperclass?->getTranslation('name', $locale),
            'attributes' => [
                'agility' => (int) $this->agility,
                'mental' => (int) $this->mental,
                'will' => (int) $this->will,
                'strength' => (int) $this->strength,
                'armor' => (int) $this->armor,
            ],
            'health' => $this->health,
            // Pasiva de la clase (si la clase la tiene) + pasiva propia
            'class_passive' => $classPassive ? [
                'name' => $this->heroClass->getTranslation('name', $locale),
                'description' => $classPassive,
            ] : null,
            'passive' => ($passiveName !== '' || $passiveDescription !== '') ? [
                'name' => $passiveName,
                'description' => $passiveDescription,
            ] : null,
            'abilities' => PublicHeroAbilityResource::collection($this->whenLoaded('heroAbilities')),
            'lore_text' => $this->getTranslation('lore_text', $locale),
            'epic_quote' => $this->getTranslation('epic_quote', $locale),
        ];
    }
}
