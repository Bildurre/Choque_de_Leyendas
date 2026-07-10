<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración de atributos de héroe (singleton, sin soft delete): límites
 * por atributo y en total, y fórmula de vida (base + atributo × multiplicador).
 */
class HeroAttributesConfiguration extends Model
{
    protected $table = 'hero_attributes_configurations';

    protected $fillable = [
        'min_attribute_value',
        'max_attribute_value',
        'min_total_attributes',
        'max_total_attributes',
        'agility_multiplier',
        'mental_multiplier',
        'will_multiplier',
        'strength_multiplier',
        'armor_multiplier',
        'total_health_base',
    ];

    /** Cache por petición: la vida se calcula héroe a héroe en los listados. */
    protected static ?self $default = null;

    protected function casts(): array
    {
        return [
            'min_attribute_value' => 'integer',
            'max_attribute_value' => 'integer',
            'min_total_attributes' => 'integer',
            'max_total_attributes' => 'integer',
            'agility_multiplier' => 'integer',
            'mental_multiplier' => 'integer',
            'will_multiplier' => 'integer',
            'strength_multiplier' => 'integer',
            'armor_multiplier' => 'integer',
            'total_health_base' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Invalida la cache al guardar (el PUT del admin).
        static::saved(function () {
            static::$default = null;
        });
    }

    /** La configuración única (la crea con los defaults si no existe). */
    public static function getDefault(): self
    {
        return static::$default ??= self::firstOrCreate([], [
            'min_attribute_value' => 1,
            'max_attribute_value' => 5,
            'min_total_attributes' => 12,
            'max_total_attributes' => 18,
            'agility_multiplier' => -1,
            'mental_multiplier' => -1,
            'will_multiplier' => 1,
            'strength_multiplier' => -1,
            'armor_multiplier' => 1,
            'total_health_base' => 25,
        ]);
    }

    /** Vida de un héroe según sus atributos (mínimo 1). */
    public function calculateHealth(int $agility, int $mental, int $will, int $strength, int $armor): int
    {
        $health = $this->total_health_base
            + $agility * $this->agility_multiplier
            + $mental * $this->mental_multiplier
            + $will * $this->will_multiplier
            + $strength * $this->strength_multiplier
            + $armor * $this->armor_multiplier;

        return max(1, $health);
    }
}
