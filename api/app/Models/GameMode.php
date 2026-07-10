<?php

namespace App\Models;

use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Modo de juego (taxonomía sin slug ni publicación; CRUD por id). Las
 * relaciones con mazos y configuraciones las añade el cluster de decks.
 */
class GameMode extends Model
{
    use HasFilters;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'game_modes';

    protected $fillable = ['name', 'description'];

    public array $translatable = ['name', 'description'];

    protected array $searchable = ['name'];
}
