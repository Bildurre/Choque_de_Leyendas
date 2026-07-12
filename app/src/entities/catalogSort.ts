import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

// Orden de los índices públicos (contrato compartido con el backend):
// `latest` (default, id desc; se omite en la URL y en la petición),
// `name` (A-Z por el nombre del locale activo) y `name_desc` (Z-A).
export const SORT_OPTIONS = ['latest', 'name', 'name_desc'] as const
export type SortOption = (typeof SORT_OPTIONS)[number]

export const SORT_LABEL_KEYS: Record<SortOption, string> = {
  latest: 'catalog.sort.latest',
  name: 'catalog.sort.nameAsc',
  name_desc: 'catalog.sort.nameDesc',
}

/** Canoniza un valor de la query al contrato (default `latest`). */
export function parseSort(value: unknown): SortOption {
  return typeof value === 'string' && (SORT_OPTIONS as readonly string[]).includes(value)
    ? (value as SortOption)
    : 'latest'
}

// Select de orden con la query string como fuente de verdad (URLs
// compartibles y botón atrás, patrón de los filtros de cartas): escribir en
// `sort` empuja a la URL con router.replace y es el cambio de query el que
// dispara la recarga en la vista (que observa `sort`). El índice de cartas
// no usa este composable: integra `sort` en su propio estado <-> query.
export function useCatalogSort() {
  const route = useRoute()
  const router = useRouter()

  const sort = computed<SortOption>({
    get: () => parseSort(route.query.sort),
    set(value) {
      router.replace({
        query: { ...route.query, sort: value === 'latest' ? undefined : value, page: undefined },
      })
    },
  })

  /** Valor para la petición al API (`undefined` en el orden por defecto). */
  const sortParam = computed(() => (sort.value === 'latest' ? undefined : sort.value))

  return { sort, sortParam }
}
