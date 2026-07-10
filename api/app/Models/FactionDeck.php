<?php

namespace App\Models;

use Edc\Core\Media\Concerns\HasImage;
use Edc\Core\Support\Concerns\HasFilters;
use Edc\Core\Support\Concerns\HasPublishedState;
use Edc\Core\Support\Concerns\ResolvesBySlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasTranslatableSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Mazo prediseñado de facción: cartas con copias y héroes, ligado a un modo
 * de juego cuyos límites (DeckAttributesConfiguration) se exigen al publicar.
 * El icono vive en MediaLibrary (colección 'image').
 */
class FactionDeck extends Model implements HasMedia
{
    use HasFilters;
    use HasImage;
    use HasPublishedState;
    use HasTranslatableSlug;
    use HasTranslations;
    use ResolvesBySlug;
    use SoftDeletes;

    protected $table = 'faction_decks';

    protected $fillable = ['name', 'slug', 'description', 'epic_quote', 'game_mode_id', 'is_published'];

    public array $translatable = ['name', 'slug', 'description', 'epic_quote'];

    protected array $searchable = ['name'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    /** Modo de juego del mazo. */
    public function gameMode(): BelongsTo
    {
        return $this->belongsTo(GameMode::class);
    }

    /** Facciones del mazo (los multifacción llevan varias). */
    public function factions(): BelongsToMany
    {
        return $this->belongsToMany(Faction::class, 'faction_deck_faction');
    }

    /** Héroes del mazo. */
    public function heroes(): BelongsToMany
    {
        return $this->belongsToMany(Hero::class, 'faction_deck_hero');
    }

    /** Cartas del mazo, con el nº de copias en el pivot. */
    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'card_faction_deck')->withPivot('copies');
    }

    /** Nº total de cartas (suma de copias). Prefiere el agregado del index. */
    public function getTotalCardsAttribute(): int
    {
        if (array_key_exists('total_cards', $this->attributes)) {
            return (int) $this->attributes['total_cards'];
        }

        return (int) $this->cards->sum('pivot.copies');
    }

    /** Nº total de héroes. Prefiere el agregado del index. */
    public function getTotalHeroesAttribute(): int
    {
        if (array_key_exists('total_heroes', $this->attributes)) {
            return (int) $this->attributes['total_heroes'];
        }

        return $this->heroes->count();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::createWithLocales(array_keys(config('motor.locales', ['es' => []])))
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
