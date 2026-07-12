<?php

namespace App\Models;

use Edc\Core\Media\Concerns\HasImage;
use Edc\Core\Previews\Concerns\HasPreviewImage;
use Edc\Core\Previews\PreviewableContract;
use Edc\Core\Support\Concerns\HasFilters;
use Edc\Core\Support\Concerns\HasPublishedState;
use Edc\Core\Support\Concerns\ResolvesBySlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * MediaLibrary (colección 'image'). Renderizable a tarjeta PNG (750x1050).
 */
class Faction extends Model implements HasMedia, PreviewableContract
{
    use HasFilters;
    use HasImage;
    use HasPreviewImage;
    use HasPublishedState;
    use HasTranslatableSlug;
    use HasTranslations;
    use ResolvesBySlug;
    use SoftDeletes;

    protected $table = 'factions';

    protected $fillable = ['name', 'slug', 'lore_text', 'epic_quote', 'color', 'is_published'];

    public array $translatable = ['name', 'slug', 'lore_text', 'epic_quote'];

    /**
     * Columnas del buscador de listados (HasFilters). LIKE sobre el json
     * completo de cada campo traducible; los campos wysiwyg (lore_text,
     * epic_quote) se buscan con su HTML tal cual — asumido y aceptable.
     */
    protected array $searchable = ['name', 'lore_text', 'epic_quote'];

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

    /** Mazos en los que participa la facción (portado del viejo). */
    public function factionDecks(): BelongsToMany
    {
        return $this->belongsToMany(FactionDeck::class, 'faction_deck_faction');
    }

    // --- Render a PNG (tarjeta de facción) ---

    /**
     * Tamaño de la tarjeta en px. El diseño viejo (_faction-preview.scss) es
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
     * icono (MediaLibrary) no es columna: el controller regenera a mano.
     * text_is_dark se deriva del color, así que color basta como disparador.
     */
    public function previewTriggerFields(): array
    {
        return ['name', 'lore_text', 'epic_quote', 'color'];
    }

    /** Payload que consume el componente de tarjeta de facción en /_render. */
    public function renderData(string $locale, ?string $type = null): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', $locale),
            'color' => $this->color,
            'text_is_dark' => (bool) $this->text_is_dark,
            'icon' => $this->imageUrl(),
            'lore_text' => $this->getTranslation('lore_text', $locale),
            'epic_quote' => $this->getTranslation('epic_quote', $locale),
            // Igual que la web pública: solo cuenta el contenido publicado.
            'heroes_count' => $this->heroes()->published()->count(),
            'cards_count' => $this->cards()->published()->count(),
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::createWithLocales(array_keys(config('motor.locales', ['es' => []])))
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
