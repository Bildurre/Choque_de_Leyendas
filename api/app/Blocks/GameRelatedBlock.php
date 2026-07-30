<?php

namespace App\Blocks;

use App\Models\Faction;
use Edc\Core\Content\BlockTypes\RelatedBlock;
use Edc\Core\Content\Models\Block;

/**
 * Bloque `related` de ESTE juego: el del motor solo ofrece las entidades
 * del registry de previews (PNG), y FACCIÓN quedó fuera al retirar sus
 * previews (su tarjeta es CSS en vivo). Aquí se re-registra la misma clave
 * añadiendo `faction` al select de entidad y resolviendo sus datos con lo
 * que necesita la tarjeta CSS del front (color, text_is_dark, icono)
 * además del contrato del catálogo (id, name, slug; preview null). El
 * componente Vue del juego (GameRelatedBlock.vue) pinta esa variante y
 * delega el resto en el BlockRelated del motor.
 */
class GameRelatedBlock extends RelatedBlock
{
    public function fields(): array
    {
        $fields = parent::fields();

        // `faction` como entidad elegible (el select del motor lista las
        // claves del registry de previews como valor => valor).
        foreach ($fields as $field) {
            if ($field->key === 'preview_key') {
                $field->options['faction'] = 'faction';
            }
        }

        return $fields;
    }

    public function resolveData(Block $block, string $locale): array
    {
        $settings = $this->localizeSettings($block->settings, $locale);

        if (($settings['preview_key'] ?? null) !== 'faction') {
            return parent::resolveData($block, $locale);
        }

        $query = Faction::published();

        ($settings['mode'] ?? 'latest') === 'random'
            ? $query->inRandomOrder()
            : $query->orderByDesc('id');

        return [
            'key' => 'faction',
            // 6 como el resto de entidades del related; la rejilla CSS del
            // front decide cuántas enseña.
            'items' => $query->limit(6)->get()
                ->map(fn (Faction $faction) => [
                    'id' => $faction->id,
                    'name' => $faction->getTranslation('name', $locale),
                    'slug' => $faction->getTranslation('slug', $locale) ?: null,
                    'preview' => null,
                    'color' => $faction->color,
                    'text_is_dark' => (bool) $faction->text_is_dark,
                    'icon' => $faction->imageUrl(),
                ])
                ->values()
                ->all(),
        ];
    }
}
