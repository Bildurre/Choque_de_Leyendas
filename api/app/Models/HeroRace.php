<?php

namespace App\Models;

use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Raza de héroe (Humano, Elfo…). Taxonomía simple: solo nombre traducible,
 * sin slug ni publicación; se resuelve por id.
 */
class HeroRace extends Model
{
    use HasFilters;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'hero_races';

    protected $fillable = ['name'];

    public array $translatable = ['name'];

    protected array $searchable = ['name'];

    /** Héroes de esta raza. */
    public function heroes(): HasMany
    {
        return $this->hasMany(Hero::class);
    }
}
