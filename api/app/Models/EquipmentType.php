<?php

namespace App\Models;

use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Tipo de equipo (taxonomía sin slug ni publicación; CRUD por id): Arma,
 * Armadura… Sus subtipos (Espada, Yelmo…) cuelgan de él. El flag uses_hands
 * (armas) decide si las cartas de ese tipo exigen manos.
 */
class EquipmentType extends Model
{
    use HasFilters;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'equipment_types';

    protected $fillable = ['name', 'uses_hands'];

    public array $translatable = ['name'];

    protected array $searchable = ['name'];

    protected function casts(): array
    {
        return [
            'uses_hands' => 'boolean',
        ];
    }

    /** Subtipos que cuelgan de este tipo (Arma → Espada, Hacha…). */
    public function subtypes(): HasMany
    {
        return $this->hasMany(EquipmentSubtype::class);
    }
}
