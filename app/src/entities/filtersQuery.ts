import { onBeforeUnmount, watch, type Ref } from 'vue'
import type { RouteLocationNormalizedLoaded, Router } from 'vue-router'

// Sincronización estado <-> query string de los filtros de los índices
// públicos (URLs compartibles y botón atrás, patrón del índice de cartas):
// la UI escribe en refs, esto los empuja a la URL con router.replace y ES
// el cambio de query el que dispara la recarga en la vista (que observa
// route.query y llama a queryToState() + load()).
//
// `fields` mapea clave de la query -> ref de estado ('' = ausente de la
// URL). Vale cualquier Ref<string>, incluidos computed con setter que
// sanea (dados 0..5, área '1'/'0', colores RGB…): al asignar un valor
// inválido el getter devuelve el canónico y el watcher limpia la URL.
export interface FiltersQueryOptions {
  route: RouteLocationNormalizedLoaded
  router: Router
  /** Refs de estado por clave de la query ('' = fuera de la URL). */
  fields: Record<string, Ref<string>>
  /** Búsqueda con debounce (viaja como ?search, sin espacios). */
  search?: Ref<string>
  /** Página actual (viaja como ?page a partir de la 2). */
  page?: Ref<number>
}

export function useFiltersQuery(options: FiltersQueryOptions) {
  const { route, router, fields, search, page } = options
  const keys = [...(search ? ['search'] : []), ...Object.keys(fields), ...(page ? ['page'] : [])]

  /** La query que representa el estado actual (los vacíos no viajan). */
  function stateToQuery(): Record<string, string> {
    const query: Record<string, string> = {}
    if (search?.value.trim()) query.search = search.value.trim()
    for (const [key, field] of Object.entries(fields)) {
      if (field.value !== '') query[key] = field.value
    }
    if (page && page.value > 1) query.page = String(page.value)
    return query
  }

  /** Vuelca la query de la URL al estado (al navegar/pegar una URL). */
  function queryToState() {
    const q = route.query
    if (search) {
      const searchQ = typeof q.search === 'string' ? q.search : ''
      // No pisar el input mientras se escribe (la query va sin espacios).
      if (search.value.trim() !== searchQ) search.value = searchQ
    }
    for (const [key, field] of Object.entries(fields)) {
      const value = typeof q[key] === 'string' ? q[key] : ''
      if (field.value !== value) field.value = value
    }
    if (page) page.value = Math.max(1, Number(q.page) || 1)
  }

  /** true si el estado ya coincide con la URL (nada que empujar). */
  function inSyncWithUrl(): boolean {
    const target = stateToQuery()
    return keys.every((key) => {
      const current = route.query[key]
      return (target[key] ?? '') === (typeof current === 'string' ? current : '')
    })
  }

  /** Empuja el estado a la URL; cambiar un filtro resetea a la página 1. */
  function pushQuery(resetPage = true) {
    if (page && resetPage) page.value = 1
    router.replace({ query: stateToQuery() })
  }

  // Búsqueda con debounce: al parar de teclear se empuja a la query.
  let debounce: ReturnType<typeof setTimeout> | undefined
  if (search) {
    watch(search, (value) => {
      clearTimeout(debounce)
      if (value.trim() === (typeof route.query.search === 'string' ? route.query.search : ''))
        return
      debounce = setTimeout(() => pushQuery(), 350)
    })
    onBeforeUnmount(() => clearTimeout(debounce))
  }

  // Filtros y orden: a la query al momento (resetean a página 1). El guard
  // evita re-empujar cuando el cambio viene de la propia URL (queryToState).
  watch(Object.values(fields), () => {
    if (!inSyncWithUrl()) pushQuery()
  })

  return { stateToQuery, queryToState, inSyncWithUrl, pushQuery }
}
