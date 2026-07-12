<?php

namespace App\Models;

use App\Models\Concerns\HasCost;
use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Habilidad activa de héroe: nombre, descripción, coste en dados (HasCost)
 * y datos de ataque opcionales. Sin slug ni publicación; se resuelve por id.
 */
class HeroAbility extends Model
{
    use HasCost;
    use HasFilters;
    use HasTranslations;
    use SoftDeletes;

    public const ATTACK_TYPES = ['physical', 'magical'];

    protected $table = 'hero_abilities';

    protected $fillable = [
        'name',
        'description',
        'attack_type',
        'attack_range_id',
        'attack_subtype_id',
        'area',
        'cost',
    ];

    public array $translatable = ['name', 'description'];

    /** Columnas del buscador de listados (HasFilters): LIKE sobre el json. */
    protected array $searchable = ['name', 'description'];

    protected function casts(): array
    {
        return ['area' => 'boolean'];
    }

    /** Rango de ataque (Melee, Alcance…), opcional. */
    public function attackRange(): BelongsTo
    {
        return $this->belongsTo(AttackRange::class);
    }

    /** Subtipo de ataque (Corte, Fuego…), opcional. */
    public function attackSubtype(): BelongsTo
    {
        return $this->belongsTo(AttackSubtype::class);
    }

    /** Héroes que tienen esta habilidad como activa. */
    public function heroes(): BelongsToMany
    {
        return $this->belongsToMany(Hero::class, 'hero_hero_ability')
            ->withPivot('position')
            ->withTimestamps();
    }

    /** Cartas que otorgan esta habilidad (cluster cards). */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
