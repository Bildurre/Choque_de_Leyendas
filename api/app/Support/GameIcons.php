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
