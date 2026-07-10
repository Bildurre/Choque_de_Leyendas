<?php

namespace App\Models;

use Edc\Core\Media\Concerns\HasImage;
use Edc\Core\Support\Concerns\HasFilters;
use Edc\Core\Support\Concerns\HasPublishedState;
use Edc\Core\Support\Concerns\ResolvesBySlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasTranslatableSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Facción del juego: color identitario, trasfondo y cita épica. Agrupa
 * héroes y cartas. `text_is_dark` se calcula al guardar por luminancia YIQ
 * del color (portado del viejo HasColorAttribute). El icono vive en
 * MediaLibrary (colección 'image').
 */
class Faction extends Model implements HasMedia
{
    use HasFilters;
    use HasImage;
    use HasPublishedState;
    use HasTranslatableSlug;
    use HasTranslations;
    use ResolvesBySlug;
    use SoftDeletes;

    protected $table = 'factions';

    protected $fillable = ['name', 'slug', 'lore_text', 'epic_quote', 'color', 'is_published'];

    public array $translatable = ['name', 'slug', 'lore_text', 'epic_quote'];

    protected array $searchable = ['name'];

    protected function casts(): array
    {
        return [
            'text_is_dark' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Texto oscuro sobre fondos claros: luminancia YIQ del color de
        // fondo (https://24ways.org/2010/calculating-color-contrast/).
        static::saving(function (Faction $faction) {
            $hex = ltrim((string) $faction->color, '#');
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
            $faction->text_is_dark = $yiq >= 128;
        });
    }

    /** Héroes de la facción. */
    public function heroes(): HasMany
    {
        return $this->hasMany(Hero::class);
    }

    /** Cartas de la facción. */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::createWithLocales(array_keys(config('motor.locales', ['es' => []])))
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
