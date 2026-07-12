// Orden de los índices públicos (contrato compartido con el backend y con
// el SortValue de los toggles del motor): `latest` (id desc), `oldest`
// (id asc), `name` (A-Z por el nombre del locale activo) y `name_desc`
// (Z-A). Cada índice tiene su orden por defecto (que se omite en la URL):
// `latest` en cartas/héroes y `name` en facciones/mazos (el histórico del
// servidor sin ?sort).
export const SORT_OPTIONS = ['latest', 'oldest', 'name', 'name_desc'] as const
export type SortOption = (typeof SORT_OPTIONS)[number]

/** Canoniza un valor de la query al contrato (default el del índice). */
export function parseSort(value: unknown, fallback: SortOption = 'latest'): SortOption {
  return typeof value === 'string' && (SORT_OPTIONS as readonly string[]).includes(value)
    ? (value as SortOption)
    : fallback
}
