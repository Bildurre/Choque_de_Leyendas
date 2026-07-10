<?php

namespace App\Models;

use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Tipo de equipo (taxonomía sin slug ni publicación; CRUD por id). La
 * categoría es una columna string validada con in:...; su etiqueta se
 * traduce en el admin (equipmentTypes.categories.*), no en servidor.
 */
class EquipmentType extends Model
{
    use HasFilters;
    use HasTranslations;
    use SoftDeletes;

    /** Categorías admitidas (validación in:... en el controller). */
    public const CATEGORIES = ['weapon', 'armor'];

    protected $table = 'equipment_types';

    protected $fillable = ['name', 'category'];

    public array $translatable = ['name'];

    protected array $searchable = ['name'];
}
