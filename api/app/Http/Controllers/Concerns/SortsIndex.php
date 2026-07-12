<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Contrato de ordenación de listados (compartido con la web pública):
 * `sort=name` ordena por nombre asc en el locale activo, `name_desc` al
 * revés y `oldest` por id asc (lo más antiguo primero). Cualquier otro
 * valor (ausente, `latest` o desconocido) cae al orden por defecto:
 * id desc (lo más reciente primero).
 */
trait SortsIndex
{
    /** Valores reconocidos por el contrato de `sort`. */
    protected const SORTS = ['name', 'name_desc', 'latest', 'oldest'];

    /** Aplica el contrato de `sort` a la query de un index. */
    protected function applySort(Builder $query, mixed $sort): Builder
    {
        $locale = app()->getLocale();

        return match ($sort) {
            'name' => $query->orderBy("name->{$locale}"),
            'name_desc' => $query->orderByDesc("name->{$locale}"),
            'oldest' => $query->orderBy('id'),
            default => $query->orderByDesc('id'),
        };
    }
}
