<?php

namespace App\Models;

use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Tipo de carta (taxonomía sin slug ni publicación; CRUD por id). Los flags
 * allows_subtypes / is_equipment sustituyen a los ids mágicos del viejo
 * (technique/spell/litany): condicionan el formulario de cartas.
 */
class CardType extends Model
{
    use HasFilters;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'card_types';

    protected $fillable = ['name', 'hero_superclass_id', 'allows_subtypes', 'is_equipment'];

    public array $translatable = ['name'];

    protected array $searchable = ['name'];

    protected function casts(): array
    {
        return [
            'allows_subtypes' => 'boolean',
            'is_equipment' => 'boolean',
        ];
    }

    /** Superclase de héroe asociada (única por tipo, nullable). */
    public function heroSuperclass(): BelongsTo
    {
        return $this->belongsTo(HeroSuperclass::class);
    }
}
