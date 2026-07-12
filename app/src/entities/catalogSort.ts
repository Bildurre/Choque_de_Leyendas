import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

// Orden de los índices públicos (contrato compartido con el backend):
// `latest` (id desc), `oldest` (id asc), `name` (A-Z por el nombre del
// locale activo) y `name_desc` (Z-A). Cada índice tiene su orden por
// defecto (que se omite en la URL): `latest` en cartas/héroes y `name`
// en facciones/mazos (el histórico del servidor sin ?sort).
export const SORT_OPTIONS = ['latest', 'oldest', 'name', 'name_desc'] as const
export type SortOption = (typeof SORT_OPTIONS)[number]

export const SORT_LABEL_KEYS: Record<SortOption, string> = {
  latest: 'catalog.sort.latest',
  oldest: 'catalog.sort.oldest',
  name: 'catalog.sort.nameAsc',
  name_desc: 'catalog.sort.nameDesc',
}

/** Canoniza un valor de la query al contrato (default el del índice). */
export function parseSort(value: unknown, fallback: SortOption = 'latest'): SortOption {
  return typeof value === 'string' && (SORT_OPTIONS as readonly string[]).includes(value)
    ? (value as SortOption)
    : fallback
}

// Orden con la query string como fuente de verdad (URLs compartibles y
// botón atrás): escribir en `sort` empuja a la URL con router.replace y es
// el cambio de query el que dispara la recarga en la vista (que observa
// `sort`). Lo usan los índices SIN más filtros en la URL (facciones); los
// demás integran `sort` en su propio estado <-> query (useFiltersQuery).
export function useCatalogSort(fallback: SortOption = 'latest') {
  const route = useRoute()
  const router = useRouter()

  const sort = computed<SortOption>({
    get: () => parseSort(route.query.sort, fallback),
    set(value) {
      router.replace({
        query: { ...route.query, sort: value === fallback ? undefined : value, page: undefined },
      })
    },
  })

  /** Valor para la petición al API (`undefined` en el orden por defecto). */
  const sortParam = computed(() => (sort.value === fallback ? undefined : sort.value))

  return { sort, sortParam }
}
