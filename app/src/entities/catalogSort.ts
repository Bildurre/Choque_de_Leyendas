// Orden de los índices públicos (contrato compartido con el backend y con
// el SortValue de los toggles del motor): `latest` (id desc), `oldest`
// (id asc), `name` (A-Z por el nombre del locale activo) y `name_desc`
// (Z-A). El orden por defecto de todos los índices (que se omite en la URL)
// es `name`: alfabético por el nombre en el locale activo, igual que el
// servidor sin ?sort.
export const SORT_OPTIONS = ['latest', 'oldest', 'name', 'name_desc'] as const
export type SortOption = (typeof SORT_OPTIONS)[number]

/** Canoniza un valor de la query al contrato (default el del índice). */
export function parseSort(value: unknown, fallback: SortOption = 'name'): SortOption {
  return typeof value === 'string' && (SORT_OPTIONS as readonly string[]).includes(value)
    ? (value as SortOption)
    : fallback
}
