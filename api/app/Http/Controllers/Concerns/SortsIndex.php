<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Contrato de ordenación de listados (compartido con la web pública):
 * `sort=name` ordena por nombre asc en el locale activo y `name_desc` al
 * revés. Cualquier otro valor (ausente, `latest` o desconocido) cae al
 * orden por defecto: id desc (lo más reciente primero).
 */
trait SortsIndex
{
    /** Aplica el contrato de `sort` a la query de un index. */
    protected function applySort(Builder $query, mixed $sort): Builder
    {
        $locale = app()->getLocale();

        return match ($sort) {
            'name' => $query->orderBy("name->{$locale}"),
            'name_desc' => $query->orderByDesc("name->{$locale}"),
            default => $query->orderByDesc('id'),
        };
    }
}
