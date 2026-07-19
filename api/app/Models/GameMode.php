<?php

namespace App\Models;

use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Modo de juego (taxonomía sin slug ni publicación; CRUD por id) CON su
 * configuración de mazos integrada: nº de cartas [min, max], copias máximas
 * por carta y nº exacto de héroes. Exactamente un modo es el por defecto
 * (is_default): hace de fallback cuando no hay modo elegido.
 */
class GameMode extends Model
{
    use HasFilters;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'game_modes';

    protected $fillable = [
        'name',
        'description',
        'min_cards',
        'max_cards',
        'max_copies_per_card',
        'required_heroes',
        'is_default',
    ];

    public array $translatable = ['name', 'description'];

    /** Columnas del buscador de listados (HasFilters): LIKE sobre el json. */
    protected array $searchable = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'min_cards' => 'integer',
            'max_cards' => 'integer',
            'max_copies_per_card' => 'integer',
            'required_heroes' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function factionDecks(): HasMany
    {
        return $this->hasMany(FactionDeck::class);
    }

    /** El modo por defecto (exactamente uno; fallback de los consumidores). */
    public static function defaultMode(): ?self
    {
        return self::where('is_default', true)->first();
    }

    /** El modo pedido o, si no existe (o es null), el por defecto. */
    public static function forMode(?int $gameModeId): ?self
    {
        if ($gameModeId !== null) {
            $mode = self::find($gameModeId);
            if ($mode) {
                return $mode;
            }
        }

        return self::defaultMode();
    }

    /**
     * Valida un mazo contra los límites de este modo. Devuelve errores
     * localizables para el admin: [['key' => 'factionDecks.validation.…',
     * 'params' => […]], …] (lista vacía = mazo válido). Las relaciones
     * cards/heroes deben venir cargadas. total_heroes cuenta héroes (cada
     * uno vale 1, sin copias); total_cards sí suma las copias del pivot.
     */
    public function validateDeck(FactionDeck $deck): array
    {
        $errors = [];
        $totalCards = $deck->total_cards;

        if ($totalCards < $this->min_cards) {
            $errors[] = ['key' => 'factionDecks.validation.minCards', 'params' => ['min' => $this->min_cards, 'total' => $totalCards]];
        }

        if ($totalCards > $this->max_cards) {
            $errors[] = ['key' => 'factionDecks.validation.maxCards', 'params' => ['max' => $this->max_cards, 'total' => $totalCards]];
        }

        $exceeded = $deck->cards->filter(fn ($card) => (int) $card->pivot->copies > $this->max_copies_per_card);
        if ($exceeded->isNotEmpty()) {
            $errors[] = ['key' => 'factionDecks.validation.maxCopies', 'params' => ['max' => $this->max_copies_per_card, 'count' => $exceeded->count()]];
        }

        if ($this->required_heroes > 0 && $deck->total_heroes !== $this->required_heroes) {
            $errors[] = ['key' => 'factionDecks.validation.requiredHeroes', 'params' => ['required' => $this->required_heroes, 'total' => $deck->total_heroes]];
        }

        return $errors;
    }
}
