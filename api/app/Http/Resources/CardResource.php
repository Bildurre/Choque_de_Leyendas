<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación para el admin: todas las traducciones, para editar. */
class CardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'slug' => $this->getTranslations('slug'),
            'lore_text' => $this->getTranslations('lore_text'),
            'epic_quote' => $this->getTranslations('epic_quote'),
            'effect' => $this->getTranslations('effect'),
            'restriction' => $this->getTranslations('restriction'),
            'image' => $this->imageUrl(),
            'faction_id' => $this->faction_id,
            'card_type_id' => $this->card_type_id,
            'card_subtype_id' => $this->card_subtype_id,
            'equipment_type_id' => $this->equipment_type_id,
            'equipment_subtype_id' => $this->equipment_subtype_id,
            'attack_type' => $this->attack_type,
            'attack_range_id' => $this->attack_range_id,
            'attack_subtype_id' => $this->attack_subtype_id,
            'hero_ability_id' => $this->hero_ability_id,
            'hands' => $this->hands,
            'cost' => $this->cost,
            'area' => (bool) $this->area,
            'is_unique' => (bool) $this->is_unique,
            // Relaciones en mínimo (id + nombre + extras del selector): sus
            // Resources completos son de otros clusters.
            'faction' => $this->whenLoaded('faction', fn () => [
                'id' => $this->faction->id,
                'name' => $this->faction->getTranslations('name'),
                'color' => $this->faction->color,
            ]),
            'card_type' => $this->whenLoaded('cardType', fn () => [
                'id' => $this->cardType->id,
                'name' => $this->cardType->getTranslations('name'),
                'allows_subtypes' => (bool) $this->cardType->allows_subtypes,
                'is_equipment' => (bool) $this->cardType->is_equipment,
            ]),
            'card_subtype' => $this->whenLoaded('cardSubtype', fn () => [
                'id' => $this->cardSubtype->id,
                'name' => $this->cardSubtype->getTranslations('name'),
            ]),
            'equipment_type' => $this->whenLoaded('equipmentType', fn () => [
                'id' => $this->equipmentType->id,
                'name' => $this->equipmentType->getTranslations('name'),
                'uses_hands' => (bool) $this->equipmentType->uses_hands,
            ]),
            'equipment_subtype' => $this->whenLoaded('equipmentSubtype', fn () => [
                'id' => $this->equipmentSubtype->id,
                'name' => $this->equipmentSubtype->getTranslations('name'),
            ]),
            'attack_range' => $this->whenLoaded('attackRange', fn () => [
                'id' => $this->attackRange->id,
                'name' => $this->attackRange->getTranslations('name'),
            ]),
            'attack_subtype' => $this->whenLoaded('attackSubtype', fn () => [
                'id' => $this->attackSubtype->id,
                'name' => $this->attackSubtype->getTranslations('name'),
            ]),
            // Habilidad completa (tipado + coste + descripción): el single
            // del admin la pinta como el render de la carta.
            'hero_ability' => $this->whenLoaded('heroAbility', fn () => [
                'id' => $this->heroAbility->id,
                'name' => $this->heroAbility->getTranslations('name'),
                'description' => $this->heroAbility->getTranslations('description'),
                'cost' => $this->heroAbility->cost,
                'attack_type' => $this->heroAbility->attack_type,
                'area' => (bool) $this->heroAbility->area,
                'attack_range' => $this->heroAbility->attackRange ? [
                    'id' => $this->heroAbility->attackRange->id,
                    'name' => $this->heroAbility->attackRange->getTranslations('name'),
                ] : null,
                'attack_subtype' => $this->heroAbility->attackSubtype ? [
                    'id' => $this->heroAbility->attackSubtype->id,
                    'name' => $this->heroAbility->attackSubtype->getTranslations('name'),
                ] : null,
            ]),
            'is_published' => $this->is_published,
            'previews' => $this->previewUrls(),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
