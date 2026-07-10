<?php

namespace App\Models;

use Edc\Core\Support\Concerns\HasFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Límites de construcción de mazos por modo de juego (sin soft delete):
 * nº de cartas [min, max], copias máximas por carta y nº exacto de héroes.
 */
class DeckAttributesConfiguration extends Model
{
    use HasFilters;

    protected $table = 'deck_attributes_configurations';

    protected $fillable = [
        'game_mode_id',
        'min_cards',
        'max_cards',
        'max_copies_per_card',
        'required_heroes',
    ];

    protected function casts(): array
    {
        return [
            'min_cards' => 'integer',
            'max_cards' => 'integer',
            'max_copies_per_card' => 'integer',
            'required_heroes' => 'integer',
        ];
    }

    /** Modo de juego al que aplica (null = configuración genérica). */
    public function gameMode(): BelongsTo
    {
        return $this->belongsTo(GameMode::class);
    }

    /** Configuración para un modo (o la genérica sin modo, si no hay). */
    public static function forMode(?int $gameModeId): ?self
    {
        if ($gameModeId !== null) {
            $config = self::where('game_mode_id', $gameModeId)->first();
            if ($config) {
                return $config;
            }
        }

        return self::whereNull('game_mode_id')->first();
    }

    /**
     * Valida un mazo contra estos límites. Devuelve errores localizables
     * para el admin: [['key' => 'factionDecks.validation.…', 'params' => […]], …]
     * (lista vacía = mazo válido). Las relaciones cards/heroes deben venir cargadas.
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
