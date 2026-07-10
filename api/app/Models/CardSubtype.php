<?php

namespace App\Models;

use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/** Subtipo de carta (taxonomía sin slug ni publicación; CRUD por id). */
class CardSubtype extends Model
{
    use HasFilters;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'card_subtypes';

    protected $fillable = ['name'];

    public array $translatable = ['name'];

    protected array $searchable = ['name'];
}
