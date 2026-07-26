<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Filtros de listado con varios valores: cada clave de filtro acepta
 * escalar (?faction_id=3) o array (?faction_id[]=1&faction_id[]=2) y el
 * index filtra con whereIn (los multiselects del admin y de la web pública
 * mandan la forma array; los enlaces entrantes siguen mandando escalar).
 * Aquí viven los normalizadores comunes.
 */
trait FiltersByIds
{
    /**
     * Ids del filtro `$key`: normaliza escalar o array, descarta vacíos y
     * castea a int (solo positivos). [] = el filtro no viaja (no filtrar).
     */
    protected function idsFrom(Request $request, string $key): array
    {
        $raw = $request->input($key);

        return array_values(array_filter(
            array_map(
                fn ($value) => is_scalar($value) ? (int) $value : 0,
                is_array($raw) ? $raw : [$raw],
            ),
            fn (int $id) => $id > 0,
        ));
    }

    /**
     * Valores string del filtro `$key` (enums tipo attack_type o area):
     * normaliza escalar o array, descarta vacíos y, si se pasa `$allowed`,
     * acota a esos valores. [] = no filtrar.
     */
    protected function valuesFrom(Request $request, string $key, ?array $allowed = null): array
    {
        $raw = $request->input($key);
        $values = array_values(array_filter(
            is_array($raw) ? $raw : [$raw],
            fn ($value) => is_string($value) && $value !== '',
        ));

        return $allowed === null
            ? $values
            : array_values(array_intersect($values, $allowed));
    }
}
