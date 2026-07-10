<?php

namespace App\Models;

use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Superclase de héroe (Combatiente, Conjurador…). Taxonomía simple: solo
 * nombre traducible, sin slug ni publicación; se resuelve por id.
 */
class HeroSuperclass extends Model
{
    use HasFilters;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'hero_superclasses';

    protected $fillable = ['name'];

    public array $translatable = ['name'];

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
