<?php

namespace App\Models;

use App\Models\Concerns\HasCost;
use App\Support\GameIcons;
use Edc\Core\Media\Concerns\HasImage;
use Edc\Core\Previews\Concerns\HasPreviewImage;
use Edc\Core\Previews\PreviewableContract;
use Edc\Core\Support\Concerns\HasFilters;
use Edc\Core\Support\Concerns\HasPublishedState;
use Edc\Core\Support\Concerns\ResolvesBySlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasTranslatableSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Carta jugable: coste en dados (HasCost), tipo obligatorio y el resto de
 * relaciones opcionales. Los flags allows_subtypes/is_equipment de su tipo
 * deciden qué campos aplican (sustituyen a los ids mágicos del viejo).
 * Renderizable a carta PNG (750x1050).
 */
class Card extends Model implements HasMedia, PreviewableContract
{
    use HasCost;
    use HasFilters;
    use HasImage;
    use HasPreviewImage;
    use HasPublishedState;
    use HasTranslatableSlug;
    use HasTranslations;
    use ResolvesBySlug;
    use SoftDeletes;

    /** Tipos de ataque admitidos (validación in:... en el controller). */
    public const ATTACK_TYPES = ['physical', 'magical'];

    /** Manos admitidas para equipo (validación in:... en el controller). */
    public const HANDS = [1, 2];

    protected $table = 'cards';

    protected $fillable = [
        'name',
        'slug',
        'lore_text',
        'epic_quote',
        'effect',
        'restriction',
        'faction_id',
        'card_type_id',
        'card_subtype_id',
        'equipment_type_id',
        'attack_type',
        'attack_range_id',
        'attack_subtype_id',
        'hero_ability_id',
        'hands',
        'cost',
        'area',
        'is_unique',
        'is_published',
    ];

    public array $translatable = [
        'name',
        'slug',
        'lore_text',
        'epic_quote',
        'effect',
        'restriction',
    ];

    protected array $searchable = ['name'];

    protected function casts(): array
    {
        return [
            'hands' => 'integer',
            'area' => 'boolean',
            'is_unique' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    /** Facción de la carta (opcional). */
    public function faction(): BelongsTo
    {
        return $this->belongsTo(Faction::class);
    }

    /** Tipo de carta (obligatorio); sus flags condicionan el resto. */
    public function cardType(): BelongsTo
    {
        return $this->belongsTo(CardType::class);
    }

    /** Subtipo (solo si el tipo allows_subtypes). */
    public function cardSubtype(): BelongsTo
    {
        return $this->belongsTo(CardSubtype::class);
    }

    /** Tipo de equipo (solo si el tipo is_equipment). */
    public function equipmentType(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class);
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

    /** Habilidad de héroe que otorga la carta (opcional). */
    public function heroAbility(): BelongsTo
    {
        return $this->belongsTo(HeroAbility::class);
    }

    // --- Render a PNG (carta) ---

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

    /** Cambios que invalidan la preview (declarativo; is_published no). */
    public function previewTriggerFields(): array
    {
        return [
            'name',
            'lore_text',
            'epic_quote',
            'effect',
            'restriction',
            'faction_id',
            'card_type_id',
            'card_subtype_id',
            'equipment_type_id',
            'attack_type',
            'attack_range_id',
            'attack_subtype_id',
            'hero_ability_id',
            'hands',
            'cost',
            'area',
            'is_unique',
        ];
    }

    /** Payload que consume el componente de carta en /_render. */
    public function renderData(string $locale, ?string $type = null): array
    {
        $this->loadMissing([
            'faction',
            'cardType',
            'cardSubtype',
            'equipmentType',
            'attackRange',
            'attackSubtype',
        ]);

        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', $locale),
            'image' => $this->imageUrl(),
            // Iconos convencionales del gestor (url|null; el render omite los null)
            'icons' => GameIcons::urls([
                'physical', 'magical', 'area',
                'dice-red', 'dice-green', 'dice-blue',
                'hands-1', 'hands-2',
            ]),
            'cost' => $this->cost,
            'cost_parsed' => $this->parsed_cost,
            'type' => $this->cardType?->getTranslation('name', $locale),
            // Flags del tipo: deciden qué pinta el componente (subtipo/equipo)
            'type_allows_subtypes' => (bool) $this->cardType?->allows_subtypes,
            'type_is_equipment' => (bool) $this->cardType?->is_equipment,
            'subtype' => $this->cardSubtype?->getTranslation('name', $locale),
            'equipment_type' => $this->equipmentType?->getTranslation('name', $locale),
            'hands' => $this->hands,
            'attack' => [
                // La clave se localiza en el componente de render
                'type' => $this->attack_type,
                'range' => $this->attackRange?->getTranslation('name', $locale),
                'subtype' => $this->attackSubtype?->getTranslation('name', $locale),
            ],
            'area' => (bool) $this->area,
            'is_unique' => (bool) $this->is_unique,
            'effect' => $this->getTranslation('effect', $locale),
            'restriction' => $this->getTranslation('restriction', $locale),
            'lore_text' => $this->getTranslation('lore_text', $locale),
            'epic_quote' => $this->getTranslation('epic_quote', $locale),
            'faction' => $this->faction ? [
                'name' => $this->faction->getTranslation('name', $locale),
                'color' => $this->faction->color,
                'text_is_dark' => (bool) $this->faction->text_is_dark,
                'icon_url' => $this->faction->imageUrl(),
            ] : null,
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::createWithLocales(array_keys(config('motor.locales', ['es' => []])))
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
