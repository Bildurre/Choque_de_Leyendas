<?php

namespace App\Blocks;

use App\Models\GameMode;
use Edc\Core\Content\BlockType;
use Edc\Core\Content\Fields\Field;
use Edc\Core\Content\Models\Block;

/**
 * Bloque con-datos de ESTE juego (portado del game-modes viejo): lista TODOS
 * los modos de juego con su configuración de mazos y el nº de mazos
 * publicados; la app enlaza al índice de mazos.
 */
class GameModesBlock extends BlockType
{
    public static string $key = 'game-modes';

    public string $name = 'Modos de juego';

    public string $icon = 'dices';

    public string $category = 'data';

    public function fields(): array
    {
        return [
            Field::text('title')->label('Título')->translatable(),
            Field::text('subtitle')->label('Subtítulo')->translatable(),
            Field::richtext('intro')->label('Introducción')->translatable(),
        ];
    }

    public function resolveData(Block $block, string $locale): array
    {
        return [
            'modes' => GameMode::query()
                ->with('deckConfiguration')
                ->withCount(['factionDecks' => fn ($q) => $q->published()])
                ->orderBy("name->{$locale}")
                ->get()
                ->map(fn (GameMode $mode) => [
                    'id' => $mode->id,
                    'name' => $mode->getTranslation('name', $locale),
                    'description' => $mode->getTranslation('description', $locale),
                    'config' => $mode->deckConfiguration ? [
                        'min_cards' => $mode->deckConfiguration->min_cards,
                        'max_cards' => $mode->deckConfiguration->max_cards,
                        'max_copies_per_card' => $mode->deckConfiguration->max_copies_per_card,
                        'required_heroes' => $mode->deckConfiguration->required_heroes,
                    ] : null,
                    'decks_count' => $mode->faction_decks_count,
                ])
                ->all(),
        ];
    }
}
