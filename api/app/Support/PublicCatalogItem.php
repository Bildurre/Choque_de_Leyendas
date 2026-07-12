<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Ítem de catálogo público {id, name, slug, preview}: mismo contrato que
 * Edc\Core\Previews\CatalogItem del motor (/api/catalog/{key}). El vendor
 * instalado aún no trae esa clase; cuando se actualice a la versión con el
 * catálogo puede sustituirse este helper por el del motor.
 */
class PublicCatalogItem
{
    /**
     * Serializa un modelo al ítem: id, name y slug localizados y URL de la
     * preview PNG (null si no está generada — el fallback visual es del front).
     *
     * @return array{id: int|string, name: string, slug: string|null, preview: string|null}
     */
    public static function fromModel(Model $model, string $key, string $locale): array
    {
        return [
            'id' => $model->getKey(),
            'name' => (string) $model->getTranslation('name', $locale),
            'slug' => $model->getTranslation('slug', $locale) ?: null,
            'preview' => method_exists($model, 'previewUrl')
                ? $model->previewUrl($locale, $key)
                : null,
        ];
    }
}
