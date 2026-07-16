<?php

namespace App\Http\Resources;

use App\Models\HeroAbility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class HeroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'slug' => $this->getTranslations('slug'),
            'lore_text' => $this->getTranslations('lore_text'),
            'epic_quote' => $this->getTranslations('epic_quote'),
            'passive_name' => $this->getTranslations('passive_name'),
            'passive_description' => $this->getTranslations('passive_description'),
            'image' => $this->imageUrl(),
            'faction_id' => $this->faction_id,
            'hero_race_id' => $this->hero_race_id,
            'hero_class_id' => $this->hero_class_id,
            // Facción en mínimo (id + nombre + color): su Resource es de
            // otro cluster. El color tiñe la tarjeta del listado.
            'faction' => $this->whenLoaded('faction', fn () => [
                'id' => $this->faction->id,
                'name' => $this->faction->getTranslations('name'),
                'color' => $this->faction->color,
            ]),
            'hero_race' => new HeroRaceResource($this->whenLoaded('heroRace')),
            'hero_class' => new HeroClassResource($this->whenLoaded('heroClass')),
            // Nombres YA resueltos con el género del héroe, por locale (para
            // pintarlos junto al héroe; los mapas de arriba son para editar).
            'race_display' => $this->whenLoaded('heroRace', fn () => $this->heroRace->namesForGender($this->gender)),
            'class_display' => $this->whenLoaded('heroClass', fn () => $this->heroClass->namesForGender($this->gender)),
            'gender' => $this->gender,
            'agility' => $this->agility,
            'mental' => $this->mental,
            'will' => $this->will,
            'strength' => $this->strength,
            'armor' => $this->armor,
            // Derivados (config de atributos): los pinta el admin, no se editan
            'health' => $this->health,
            'total_attributes' => $this->total_attributes,
            // Habilidades activas con su posición del pivot (ya ordenadas)
            'abilities' => $this->whenLoaded('heroAbilities', fn () => $this->heroAbilities->map(
                fn (HeroAbility $ability) => [
                    'id' => $ability->id,
                    'name' => $ability->getTranslations('name'),
                    'description' => $ability->getTranslations('description'),
                    'cost' => $ability->cost,
                    'attack_type' => $ability->attack_type,
                    'area' => (bool) $ability->area,
                    'position' => (int) $ability->pivot->position,
                ],
            )),
            'is_published' => $this->is_published,
            'previews' => $this->previewUrls(),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
