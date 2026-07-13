import { computed, onBeforeUnmount, reactive, ref, watch, type Component } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
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
  const sort = ref<SortValue>('latest')
  // Filtros genéricos de la vista (clave → valor; '' = sin filtrar). La vista
  // hace v-model sobre sus claves (selects en el panel derecho, slot
  // `filters` del EntityPanel) y el listado se relanza solo al cambiar.
  const filters = reactive<Record<string, string>>({})

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

  /** Filtros con valor (los vacíos no viajan en la query). */
  function activeFilters(): Record<string, string> {
    return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''))
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

  // Búsqueda, orden y filtros comparten debounce y vuelven a la página 1.
  let timer: ReturnType<typeof setTimeout> | null = null
  watch([search, sort, filters], () => {
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

  async function init() {
    await locales.load()
    await load()
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
