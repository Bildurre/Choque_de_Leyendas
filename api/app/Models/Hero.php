<?php

namespace App\Models;

use App\Support\GameIcons;
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
 * Héroe: personaje jugable con atributos, pasiva y habilidades activas
 * ordenadas. La vida es derivada (base + atributo × multiplicador según
 * HeroAttributesConfiguration), no se almacena. Renderizable a carta PNG.
 */
class Hero extends Model implements HasMedia, PreviewableContract
{
    use HasFilters;
    use HasImage;
    use HasPreviewImage;
    use HasPublishedState;
    use HasTranslatableSlug;
    use HasTranslations;
    use ResolvesBySlug;
    use SoftDeletes;

    protected $table = 'heroes';

    protected $fillable = [
        'name',
        'slug',
        'lore_text',
        'epic_quote',
        'passive_name',
        'passive_description',
        'faction_id',
        'hero_race_id',
        'hero_class_id',
        'gender',
        'agility',
        'mental',
        'will',
        'strength',
        'armor',
        'is_published',
    ];

    public array $translatable = [
        'name',
        'slug',
        'lore_text',
        'epic_quote',
        'passive_name',
        'passive_description',
    ];

    /**
     * Columnas del buscador de listados (HasFilters). LIKE sobre el json
     * completo de cada campo traducible; los campos wysiwyg (lore_text,
     * epic_quote) se buscan con su HTML tal cual — asumido y aceptable.
     */
    // Solo campos "de juego": el lore y la cita quedan fuera de la búsqueda.
    protected array $searchable = ['name', 'passive_name', 'passive_description'];

    protected function casts(): array
    {
        return [
            'agility' => 'integer',
            'mental' => 'integer',
            'will' => 'integer',
            'strength' => 'integer',
            'armor' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /** Facción del héroe (opcional). */
    public function faction(): BelongsTo
    {
        return $this->belongsTo(Faction::class);
    }

    /** Raza del héroe (opcional). */
    public function heroRace(): BelongsTo
    {
        return $this->belongsTo(HeroRace::class);
    }

    /** Clase del héroe (opcional); su superclase llega a través de ella. */
    public function heroClass(): BelongsTo
    {
        return $this->belongsTo(HeroClass::class);
    }

    /** Habilidades activas, ordenadas por la posición del pivot. */
    public function heroAbilities(): BelongsToMany
    {
        return $this->belongsToMany(HeroAbility::class, 'hero_hero_ability')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('hero_hero_ability.position');
    }

    /** Vida derivada según la configuración de atributos (mínimo 1). */
    public function getHealthAttribute(): int
    {
        return HeroAttributesConfiguration::getDefault()->calculateHealth(
            (int) $this->agility,
            (int) $this->mental,
            (int) $this->will,
            (int) $this->strength,
            (int) $this->armor,
        );
    }

    /** Puntos de atributo repartidos en total. */
    public function getTotalAttributesAttribute(): int
    {
        return (int) $this->agility + (int) $this->mental + (int) $this->will
            + (int) $this->strength + (int) $this->armor;
    }

    // --- Render a PNG (carta de héroe) ---

    /** Tamaño del componente carta en px (63x88 mm aprox. @300dpi). */
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
     * pivot de habilidades no es columna: el controller regenera a mano.
     */
    public function previewTriggerFields(): array
    {
        return [
            'name',
            'lore_text',
            'epic_quote',
            'passive_name',
            'passive_description',
            'faction_id',
            'hero_race_id',
            'hero_class_id',
            'gender',
            'agility',
            'mental',
            'will',
            'strength',
            'armor',
        ];
    }

    /** Payload que consume el componente de carta de héroe en /_render. */
    public function renderData(string $locale, ?string $type = null): array
    {
        $this->loadMissing([
            'faction',
            'heroRace',
            'heroClass.heroSuperclass',
            'heroAbilities.attackRange',
            'heroAbilities.attackSubtype',
        ]);

        $classPassive = $this->heroClass?->getTranslation('passive', $locale);

        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', $locale),
            'image' => $this->imageUrl(),
            // Iconos convencionales del gestor (url|null; el render omite los null)
            'icons' => GameIcons::urls([
                'agility', 'mental', 'will', 'strength', 'armor', 'health',
                'physical', 'magical', 'area',
                'dice-red', 'dice-green', 'dice-blue',
                // Logo del pie (el viejo pintaba ahí su hada); si no está
                // subido el pie se queda como estaba, sin hueco.
                'faerie',
            ]),
            // El hada inlineada (SVG con currentColor: por <img> saldría en
            // negro; inline hereda el color del texto del pie).
            'faerie_svg' => GameIcons::inlineSvg('faerie'),
            'attributes' => [
                'agility' => (int) $this->agility,
                'mental' => (int) $this->mental,
                'will' => (int) $this->will,
                'strength' => (int) $this->strength,
                'armor' => (int) $this->armor,
            ],
            'health' => $this->health,
            'gender' => $this->gender,
            // Nombres de taxonomía con el género del héroe (HasGenderedName)
            'race' => $this->heroRace?->nameForGender($this->gender, $locale),
            'class' => $this->heroClass?->nameForGender($this->gender, $locale),
            'superclass' => $this->heroClass?->heroSuperclass?->nameForGender($this->gender, $locale),
            // Pasiva de la clase (el viejo la pintaba antes que la propia)
            'class_passive' => $classPassive ? [
                'name' => $this->heroClass->nameForGender($this->gender, $locale),
                'description' => $classPassive,
            ] : null,
            'passive' => [
                'name' => $this->getTranslation('passive_name', $locale),
                'description' => $this->getTranslation('passive_description', $locale),
            ],
            'abilities' => $this->heroAbilities->map(fn (HeroAbility $ability) => [
                'name' => $ability->getTranslation('name', $locale),
                'cost' => $ability->cost,
                'cost_parsed' => $ability->parsed_cost,
                'attack' => [
                    // La clave se localiza en el componente de render
                    'type' => $ability->attack_type,
                    'range' => $ability->attackRange?->getTranslation('name', $locale),
                    'subtype' => $ability->attackSubtype?->getTranslation('name', $locale),
                ],
                'area' => (bool) $ability->area,
                'description' => $ability->getTranslation('description', $locale),
            ])->values()->all(),
            'faction' => $this->faction ? [
                'name' => $this->faction->getTranslation('name', $locale),
                'color' => $this->faction->color,
                'text_is_dark' => (bool) $this->faction->text_is_dark,
                'icon_url' => $this->faction->imageUrl(),
            ] : null,
            'lore_text' => $this->getTranslation('lore_text', $locale),
            'epic_quote' => $this->getTranslation('epic_quote', $locale),
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::createWithLocales(array_keys(config('motor.locales', ['es' => []])))
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
