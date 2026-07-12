<?php

namespace App\Models;

use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Clase de héroe (Guerrero, Mago…). Nombre y pasiva traducibles; pertenece
 * (opcionalmente) a una superclase. Se resuelve por id.
 */
class HeroClass extends Model
{
    use HasFilters;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'hero_classes';

    protected $fillable = ['name', 'passive', 'hero_superclass_id'];

    public array $translatable = ['name', 'passive'];

    /** Columnas del buscador de listados (HasFilters): LIKE sobre el json. */
    protected array $searchable = ['name', 'passive'];

    /** Superclase a la que pertenece la clase. */
    public function heroSuperclass(): BelongsTo
    {
        return $this->belongsTo(HeroSuperclass::class);
    }

    /** Héroes de esta clase. */
    public function heroes(): HasMany
    {
        return $this->hasMany(Hero::class);
    }
}
