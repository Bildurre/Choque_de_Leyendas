<?php

namespace App\Models;

use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Rango de ataque (Melee, Alcance…). Taxonomía simple: solo nombre
 * traducible, sin slug ni publicación; se resuelve por id.
 */
class AttackRange extends Model
{
    use HasFilters;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'attack_ranges';

    protected $fillable = ['name'];

    public array $translatable = ['name'];

    protected array $searchable = ['name'];

    /** Habilidades de héroe con este rango. */
    public function heroAbilities(): HasMany
    {
        return $this->hasMany(HeroAbility::class);
    }

    /** Cartas con este rango. */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
