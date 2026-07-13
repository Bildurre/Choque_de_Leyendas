<?php

namespace App\Models;

use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Subtipo de equipo (taxonomía sin slug ni publicación; CRUD por id):
 * Espada, Yelmo… Todo subtipo pertenece a un tipo de equipo.
 */
class EquipmentSubtype extends Model
{
    use HasFilters;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'equipment_subtypes';

    protected $fillable = ['name', 'equipment_type_id'];

    public array $translatable = ['name'];

    protected array $searchable = ['name'];

    /** Tipo de equipo al que pertenece (obligatorio). */
    public function equipmentType(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class);
    }
}
