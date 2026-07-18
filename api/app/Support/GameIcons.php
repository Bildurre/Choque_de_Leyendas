<?php

namespace App\Support;

use Edc\Core\Icons\Models\Icon;

/**
 * Resuelve por nombre (slug) los iconos del juego subidos al gestor de Iconos
 * del motor (dados, atributos, tipos de ataque, boon/bane…). Nunca assets
 * hardcodeados: si el icono no está subido devuelve null y el render lo omite.
 */
class GameIcons
{
    /** Cache por petición: slug => url|null. */
    protected static ?array $map = null;

    /** URL del icono por nombre convencional, o null si no existe. */
    public static function url(string $name): ?string
    {
        return static::map()[$name] ?? null;
    }

    /** Mapa clave => url|null, tal cual lo espera renderData()['icons']. */
    public static function urls(array $names): array
    {
        $urls = [];
        foreach ($names as $name) {
            $urls[$name] = static::url($name);
        }

        return $urls;
    }

    /**
     * Contenido del SVG del icono, para inlinearlo en el HTML y que su
     * currentColor herede del entorno (un <img> no hereda nada y el fill
     * cae a negro). El motor sanea los SVG al subirlos. Null si el icono
     * no existe o no es SVG.
     */
    public static function inlineSvg(string $name): ?string
    {
        $media = Icon::query()->where('slug', $name)->first()?->getFirstMedia('image');
        if (! $media || strtolower($media->extension) !== 'svg') {
            return null;
        }
        $path = $media->getPath();

        return is_file($path) ? file_get_contents($path) : null;
    }

    /** Vacía la cache (tests / tras subir iconos en el mismo proceso). */
    public static function flush(): void
    {
        static::$map = null;
    }

    protected static function map(): array
    {
        return static::$map ??= Icon::query()
            ->with('media')
            ->get()
            ->mapWithKeys(fn (Icon $icon) => [$icon->slug => $icon->imageUrl()])
            ->all();
    }
}
