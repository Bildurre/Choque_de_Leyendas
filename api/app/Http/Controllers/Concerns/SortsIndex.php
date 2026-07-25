<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Contrato de ordenación de listados (compartido con la web pública):
 * `sort=name` ordena por nombre asc en el locale activo, `name_desc` al
 * revés, `latest` por id desc (lo más reciente primero) y `oldest` por id
 * asc. Cualquier otro valor (ausente o desconocido) cae al orden por
 * defecto: alfabético por el nombre en el locale activo.
 */
trait SortsIndex
{
    /** Valores reconocidos por el contrato de `sort`. */
    protected const SORTS = ['name', 'name_desc', 'latest', 'oldest'];

    /** Aplica el contrato de `sort` a la query de un index. */
    protected function applySort(Builder $query, mixed $sort): Builder
    {
        return match ($sort) {
            'name_desc' => $this->orderByName($query, desc: true),
            'latest' => $query->orderByDesc('id'),
            'oldest' => $query->orderBy('id'),
            default => $this->orderByName($query),
        };
    }

    /**
     * Alfabético por el nombre en el locale activo, insensible a mayúsculas:
     * con collation binaria (SQLite) "Zarpazo" ordenaría antes que "amuleto";
     * en MySQL la collation ci ya es insensible y el lower() no estorba.
     */
    protected function orderByName(Builder $query, bool $desc = false): Builder
    {
        $locale = app()->getLocale();
        $column = $query->getQuery()->getGrammar()->wrap("name->{$locale}");

        return $query->orderByRaw('lower('.$column.')'.($desc ? ' desc' : ''));
    }
}
