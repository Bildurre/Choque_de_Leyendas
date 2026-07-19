<?php

namespace App\Blocks;

use App\Models\Counter;
use Edc\Core\Content\BlockType;
use Edc\Core\Content\Fields\Field;
use Edc\Core\Content\Models\Block;

/**
 * Bloque con-datos de ESTE juego (portado del counters-list viejo): lista
 * TODOS los contadores publicados del tipo elegido (beneficios o
 * perjuicios), con una introducción opcional. Su componente Vue vive en la
 * app del juego.
 */
class CountersListBlock extends BlockType
{
    public static string $key = 'counters-list';

    public string $name = 'Lista de contadores';

    public string $icon = 'shield-half';

    public string $category = 'data';

    public function fields(): array
    {
        return [
            Field::text('title')->label('Título')->translatable(),
            Field::textarea('subtitle')->label('Subtítulo')->translatable(),
            Field::richtext('intro')->label('Introducción')->translatable(),
            Field::select('counter_type', [
                'boon' => 'Beneficios',
                'bane' => 'Perjuicios',
            ])->label('Tipo de contador')->default('boon'),
        ];
    }

    public function resolveData(Block $block, string $locale): array
    {
        $settings = $this->localizeSettings($block->settings, $locale);
        $type = in_array($settings['counter_type'] ?? null, ['boon', 'bane'], true)
            ? $settings['counter_type']
            : 'boon';

        return [
            'counter_type' => $type,
            'counters' => Counter::query()
                ->published()
                ->where('type', $type)
                ->orderBy("name->{$locale}")
                ->get()
                ->map(fn (Counter $counter) => [
                    'id' => $counter->id,
                    'name' => $counter->getTranslation('name', $locale),
                    'effect' => $counter->getTranslation('effect', $locale),
                    'image' => $counter->imageUrl(),
                ])
                ->all(),
        ];
    }
}
