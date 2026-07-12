<?php

namespace App\Models;

use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Modo de juego (taxonomía sin slug ni publicación; CRUD por id).
 */
class GameMode extends Model
{
    use HasFilters;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'game_modes';

    protected $fillable = ['name', 'description'];

    public array $translatable = ['name', 'description'];

    /** Columnas del buscador de listados (HasFilters): LIKE sobre el json. */
    protected array $searchable = ['name', 'description'];

    public function factionDecks(): HasMany
    {
        return $this->hasMany(FactionDeck::class);
    }

    public function deckConfiguration(): HasOne
    {
        return $this->hasOne(DeckAttributesConfiguration::class);
    }
}
