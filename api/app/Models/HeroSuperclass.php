<?php

namespace App\Models;

use App\Models\Concerns\HasGenderedName;
use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Superclase de héroe (Combatiente, Conjurador…). Taxonomía simple: nombre
 * traducible (con femenino opcional), sin slug ni publicación; por id.
 */
class HeroSuperclass extends Model
{
    use HasFilters;
    use HasGenderedName;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'hero_superclasses';

    protected $fillable = ['name', 'name_female'];

    public array $translatable = ['name', 'name_female'];

    protected array $searchable = ['name'];

    /** Clases de héroe que cuelgan de esta superclase. */
    public function heroClasses(): HasMany
    {
        return $this->hasMany(HeroClass::class);
    }

    /** Tipo de carta asociado a la superclase (si lo hay). */
    public function cardType(): HasOne
    {
        return $this->hasOne(CardType::class);
    }
}
