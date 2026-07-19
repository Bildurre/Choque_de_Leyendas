<?php

namespace App\Models;

use Edc\Core\Media\Concerns\HasImage;
use Edc\Core\Previews\Concerns\HasPreviewImage;
use Edc\Core\Previews\PreviewableContract;
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
 * Mazo prediseñado de facción: cartas con copias y héroes (sin copias — un
 * héroe asignado es siempre 1), ligado a un modo de juego cuyos límites
 * (columnas de configuración de GameMode) se exigen al publicar.
 * El icono vive en MediaLibrary (colección 'image'). Renderizable a tarjeta
 * PNG (750x1050).
 */
class FactionDeck extends Model implements HasMedia, PreviewableContract
{
    use HasFilters;
    use HasImage;
    use HasPreviewImage;
    use HasPublishedState;
    use HasTranslatableSlug;
    use HasTranslations;
    use ResolvesBySlug;
    use SoftDeletes;

    protected $table = 'faction_decks';

    protected $fillable = ['name', 'slug', 'description', 'epic_quote', 'game_mode_id', 'is_published'];

    public array $translatable = ['name', 'slug', 'description', 'epic_quote'];

    /**
     * Columnas del buscador de listados (HasFilters). LIKE sobre el json
     * completo de cada campo traducible; los campos wysiwyg (description,
     * epic_quote) se buscan con su HTML tal cual — asumido y aceptable.
     */
    // Solo campos "de juego": la cita épica queda fuera de la búsqueda.
    protected array $searchable = ['name', 'description'];

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

    /** Héroes del mazo (sin copias: cada héroe asignado cuenta como 1). */
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

    /** Nº total de héroes (cada uno cuenta 1). Prefiere el agregado del index. */
    public function getTotalHeroesAttribute(): int
    {
        if (array_key_exists('total_heroes', $this->attributes)) {
            return (int) $this->attributes['total_heroes'];
        }

        return $this->heroes->count();
    }

    // --- Render a PNG (tarjeta de mazo) ---

    /**
     * Tamaño de la tarjeta en px. El diseño viejo (_deck-preview.scss) es
     * fluido con aspect-ratio 5/7: mismo formato que las cartas, 750x1050.
     */
    public function previewSize(?string $type = null): array
    {
        return ['width' => 750, 'height' => 1050];
    }

    /** Etiqueta para el gestor de previews del admin. */
    public function previewLabel(string $locale): string
    {
        return $this->getTranslation('name', $locale) ?: "#{$this->id}";
    }

    /**
     * Cambios que invalidan la preview (declarativo; is_published no). El
     * icono (MediaLibrary) y las facciones/cartas/héroes (pivots) no son
     * columnas: el controller regenera a mano tras subirlo/sincronizarlos.
     */
    public function previewTriggerFields(): array
    {
        return ['name', 'description', 'epic_quote', 'game_mode_id'];
    }

    /** Payload que consume el componente de tarjeta de mazo en /_render. */
    public function renderData(string $locale, ?string $type = null): array
    {
        $this->loadMissing('gameMode');

        // Igual que la web pública: solo cuenta el contenido publicado.
        $factions = $this->factions()->published()->get();
        $heroes = $this->heroes()->published()->orderBy("name->{$locale}")->get();
        $cards = $this->cards()->published()->orderBy("name->{$locale}")->get();

        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', $locale),
            'icon' => $this->imageUrl(),
            'game_mode' => $this->gameMode?->getTranslation('name', $locale),
            // Con color para el marco (o el gradiente multifacción)
            'factions' => $factions->map(fn (Faction $faction) => [
                'name' => $faction->getTranslation('name', $locale),
                'color' => $faction->color,
                'text_is_dark' => (bool) $faction->text_is_dark,
            ])->values()->all(),
            'total_cards' => (int) $cards->sum('pivot.copies'),
            'total_heroes' => $heroes->count(),
            // Lista compacta (nombre + copias) para el reverso de la tarjeta;
            // los héroes van solo con el nombre (sin copias: siempre 1).
            'cards' => $cards->map(fn (Card $card) => [
                'name' => $card->getTranslation('name', $locale),
                'copies' => (int) $card->pivot->copies,
            ])->values()->all(),
            'heroes' => $heroes->map(fn (Hero $hero) => [
                'name' => $hero->getTranslation('name', $locale),
            ])->values()->all(),
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::createWithLocales(array_keys(config('motor.locales', ['es' => []])))
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
