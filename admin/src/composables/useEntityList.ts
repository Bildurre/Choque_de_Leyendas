import { computed, nextTick, onBeforeUnmount, reactive, ref, watch, type Component } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { CircleCheck, FilePen, LayoutGrid, Trash } from '@lucide/vue'
import { useCardDeselect, useResource, useRightSidebar } from '@edc-motor/admin-kit'
import { useConfirm, useToast, type SortValue } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'
import type { EntityListItem, Translations } from '@juego/shared'

export interface EntityListOptions<T> {
  /** Ruta base de la API de admin (p. ej. '/admin/houses'). */
  resource: string
  /** Namespace de i18n de la entidad (p. ej. 'houses'). */
  ns: string
  /** Nombre de la ruta del detalle (omítelo si la entidad no tiene single). */
  singleRoute?: string
  /** Campo "nombre" del ítem, para los mensajes de confirmación. */
  nameOf: (item: T) => Translations
  /** Clave del PreviewRegistry si la entidad es renderizable a PNG. */
  previewKey?: string
  /** Cómo se resuelve la entidad en la API: por slug (defecto) o por id. */
  resolveBy?: 'slug' | 'id'
  /**
   * Tabs de estado del listado. Por defecto published/draft/trashed; las
   * taxonomías sin is_published usan ['all', 'trashed'] ('all' no filtra
   * en el servidor). Las etiquetas salen de `<ns>.tabs.<key>`.
   */
  tabKeys?: string[]
  /**
   * Parámetros extra que viajan en cada list() (filtros propios de la vista,
   * p. ej. `type` en contadores). La vista relanza load(1) cuando cambien.
   */
  extraParams?: () => Record<string, string | undefined>
  /**
   * Claves de filtro que pueden llegar en la query de la ruta (enlaces
   * entrantes desde otros índices/singles): en init() se copian a
   * `filters` ANTES de la primera carga. Cada clave acepta un valor único
   * (?faction_id=3) o una lista separada por comas (?faction_id=3,5).
   * Además, toda vista acepta `?search=` (inicializa la búsqueda) y
   * `?selected=<id>` (selecciona ese ítem tras la primera carga, si está
   * en la página).
   */
  queryFilters?: string[]
}

/** Icono de cada tab de estado conocida. */
const TAB_ICONS: Record<string, Component> = {
  all: LayoutGrid,
  published: CircleCheck,
  draft: FilePen,
  trashed: Trash,
}

/**
 * Lógica común de los listados de entidades del admin: filtros + tabs +
 * búsqueda con debounce, modal de alta/edición, y acciones de fila
 * (publicar, papelera, restaurar, borrado definitivo) con confirmación,
 * toasts y manejo de errores. Cada vista pone solo su template.
 */
export function useEntityList<T extends EntityListItem>(options: EntityListOptions<T>) {
  const { t } = useI18n()
  const router = useRouter()
  const route = useRoute()
  const locales = useLocalesStore()
  const toast = useToast()
  const { confirm } = useConfirm()
  const { items, meta, loading, list, remove, action } = useResource<T>(api, options.resource)

  // Selección → panel derecho (patrón kontuan): la tarjeta entera selecciona
  // y el panel trae TODAS las acciones + info del elemento.
  const sidebar = useRightSidebar()
  sidebar.useRegister(t(`${options.ns}.panelTitle`))
  const selectedId = ref<number | null>(null)
  const selected = computed(() => items.value.find((i) => i.id === selectedId.value) ?? null)

  function select(item: T) {
    selectedId.value = item.id
    sidebar.reveal()
  }

  // Click en la zona vacía del contenido (fuera de una card o control):
  // deselecciona y el panel derecho vuelve a su estado sin selección
  // (en las vistas con filtros, los selects del panel).
  useCardDeselect(() => (selectedId.value = null))

  const tabKeys = options.tabKeys ?? ['published', 'draft', 'trashed']
  const status = ref(tabKeys[0] ?? 'published')
  const search = ref('')
  // Ordenación del contrato compartido con la API (toggles del IndexToolbar).
  // Por defecto alfabético por el nombre en el locale activo.
  const sort = ref<SortValue>('name')
  // Filtros genéricos de la vista (clave → valores marcados; [] = sin
  // filtrar). La vista hace v-model sobre sus claves (MultiSelect en el
  // panel derecho, slot `filters` del EntityPanel) y el listado se relanza
  // solo al cambiar. En la petición cada array viaja como `clave[]=` (la
  // serialización por defecto de axios) y el servidor filtra con whereIn.
  const filters = reactive<Record<string, string[]>>({})

  const tabs = computed(() =>
    tabKeys.map((key) => ({ key, label: t(`${options.ns}.tabs.${key}`), icon: TAB_ICONS[key] })),
  )

  /** Valor traducible en el locale activo (con fallback al default). */
  function tr(obj: Translations | null | undefined): string {
    if (!obj) return '—'
    return obj[locales.current] || obj[locales.defaultLocale] || Object.values(obj)[0] || '—'
  }

  /** Slug del locale activo (para URLs de detalle/edición). */
  function slugFor(item: T): string {
    return item.slug?.[locales.current] || Object.values(item.slug || {})[0] || ''
  }

  /** Clave con la que la API resuelve el ítem (slug o id, según opciones). */
  function keyFor(item: T): string {
    return options.resolveBy === 'id' ? String(item.id) : slugFor(item)
  }

  /** Filtros con algún valor marcado (los vacíos no viajan en la query). */
  function activeFilters(): Record<string, string[]> {
    return Object.fromEntries(Object.entries(filters).filter(([, value]) => value.length > 0))
  }

  /** Vacía TODOS los filtros de la vista (botón "Limpiar filtros"). */
  function clearFilters() {
    for (const key of Object.keys(filters)) filters[key] = []
  }

  async function load(page = 1) {
    try {
      await list({
        search: search.value,
        status: status.value,
        sort: sort.value,
        ...activeFilters(),
        page,
        ...(options.extraParams?.() ?? {}),
      })
    } catch {
      toast.danger(t('common.errors.load'))
    }
  }

  function reloadPage() {
    load(meta.value?.current_page ?? 1)
  }

  // Página actual / total para BasePagination: escribir en `page` navega
  // (v-model:page). Búsqueda, orden, filtros y tabs ya vuelven a la 1 (load(1)).
  const page = computed({
    get: () => meta.value?.current_page ?? 1,
    set: (n: number) => {
      load(n)
    },
  })
  const pages = computed(() => meta.value?.last_page ?? 1)

  watch(status, () => {
    selectedId.value = null
    load(1)
  })

  // Cambiar el idioma del admin cambia el orden (y la búsqueda) en servidor:
  // se recarga la lista para verla en el alfabético del nuevo locale.
  watch(
    () => locales.current,
    () => load(1),
  )

  // Búsqueda, orden y filtros comparten debounce y vuelven a la página 1.
  // El flag silencia el watcher mientras init() copia el estado inicial de
  // la query (si no, la primera carga se duplicaría 250ms después).
  let timer: ReturnType<typeof setTimeout> | null = null
  let applyingQuery = false
  watch([search, sort, filters], () => {
    if (applyingQuery) return
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => load(1), 250)
  })
  onBeforeUnmount(() => {
    if (timer) clearTimeout(timer)
  })

  // --- Modal de creación / edición (patrón kontuan) ---
  const formOpen = ref(false)
  const formMode = ref<'create' | 'edit'>('create')
  const formSlug = ref<string | null>(null)
  // Ítem en edición: lo usan los modales de entidades sin endpoint show.
  const formItem = ref<T | null>(null)

  function openCreate() {
    formMode.value = 'create'
    formSlug.value = null
    formItem.value = null
    formOpen.value = true
  }

  function edit(item: T) {
    formMode.value = 'edit'
    formSlug.value = keyFor(item)
    formItem.value = item
    formOpen.value = true
  }

  function goSingle(item: T) {
    if (!options.singleRoute) return
    router.push({ name: options.singleRoute, params: { slug: slugFor(item) } })
  }

  function onSaved() {
    reloadPage()
  }

  // --- Acciones de fila (con confirmación, toast y errores) ---
  async function togglePublish(item: T) {
    try {
      await action(keyFor(item), 'toggle-published')
      toast.success(
        item.is_published
          ? t(`${options.ns}.toast.unpublished`)
          : t(`${options.ns}.toast.published`),
      )
      reloadPage()
    } catch {
      toast.danger(t('common.errors.action'))
    }
  }

  async function del(item: T) {
    const ok = await confirm({
      title: t(`${options.ns}.confirmDelete.title`),
      message: t(`${options.ns}.confirmDelete.message`, { name: tr(options.nameOf(item)) }),
      confirmLabel: t('common.actions.delete'),
      variant: 'danger',
    })
    if (!ok) return
    try {
      await remove(keyFor(item))
      toast.success(t(`${options.ns}.toast.deleted`))
      reloadPage()
    } catch {
      toast.danger(t('common.errors.action'))
    }
  }

  async function restore(item: T) {
    try {
      await action(item.id, 'restore')
      toast.success(t(`${options.ns}.toast.restored`))
      reloadPage()
    } catch {
      toast.danger(t('common.errors.action'))
    }
  }

  async function forceDelete(item: T) {
    const ok = await confirm({
      title: t(`${options.ns}.confirmForceDelete.title`),
      message: t(`${options.ns}.confirmForceDelete.message`, { name: tr(options.nameOf(item)) }),
      confirmLabel: t('common.actions.forceDelete'),
      variant: 'danger',
    })
    if (!ok) return
    try {
      await api.delete(`${options.resource}/${item.id}/force`)
      toast.success(t(`${options.ns}.toast.forceDeleted`))
      reloadPage()
    } catch {
      toast.danger(t('common.errors.action'))
    }
  }

  /** Encola la regeneración de los PNG del ítem (solo con previewKey). */
  async function regeneratePreview(item: T) {
    if (!options.previewKey) return
    try {
      const { data } = await api.post(`/admin/previews/${options.previewKey}/${item.id}/regenerate`)
      toast.success(data.message ?? t('previews.queued'))
    } catch {
      toast.danger(t('common.errors.action'))
    }
  }

  /**
   * Copia a `filters`/`search` el estado que llegue en la query de la ruta
   * (solo las claves permitidas en queryFilters, más `search`). Se llama con
   * el watcher silenciado: la primera carga ya sale con estos valores.
   */
  function applyQueryState() {
    const query = route.query
    for (const key of options.queryFilters ?? []) {
      const value = query[key]
      // Valor único o lista separada por comas (?faction_id=3,5).
      if (typeof value === 'string' && value !== '') {
        filters[key] = value
          .split(',')
          .map((part) => part.trim())
          .filter(Boolean)
      }
    }
    if (typeof query.search === 'string' && query.search !== '') search.value = query.search
  }

  async function init() {
    await locales.load()
    applyingQuery = true
    applyQueryState()
    // nextTick: deja pasar el flush de los watchers (pre) antes de soltarlos.
    await nextTick()
    applyingQuery = false
    await load()
    // ?selected=<id>: si el ítem cayó en la primera página, se selecciona
    // (abre el panel derecho, como un click en su card).
    const selectedQuery = Number(route.query.selected)
    if (Number.isInteger(selectedQuery) && selectedQuery > 0) {
      // Cast: useResource desenvuelve T en el ref (UnwrapRefSimple).
      const found = items.value.find((i) => i.id === selectedQuery) as T | undefined
      if (found) select(found)
    }
  }

  return {
    t,
    locales,
    items,
    meta,
    loading,
    page,
    pages,
    status,
    search,
    sort,
    filters,
    clearFilters,
    tabs,
    tr,
    slugFor,
    load,
    init,
    selectedId,
    selected,
    select,
    hasPreview: !!options.previewKey,
    formOpen,
    formMode,
    formSlug,
    formItem,
    openCreate,
    edit,
    goSingle,
    onSaved,
    togglePublish,
    del,
    restore,
    forceDelete,
    regeneratePreview,
  }
}
