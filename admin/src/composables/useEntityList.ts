import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { CircleCheck, FilePen, Trash } from '@lucide/vue'
import { useResource, useRightSidebar } from '@edc-motor/admin-kit'
import { useConfirm, useToast } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'
import type { EntityBase, Translations } from '@juego/shared'

export interface EntityListOptions<T> {
  /** Ruta base de la API de admin (p. ej. '/admin/houses'). */
  resource: string
  /** Namespace de i18n de la entidad (p. ej. 'houses'). */
  ns: string
  /** Nombre de la ruta del detalle (p. ej. 'house-single'). */
  singleRoute: string
  /** Campo "nombre" del ítem, para los mensajes de confirmación. */
  nameOf: (item: T) => Translations
  /** Clave del PreviewRegistry si la entidad es renderizable a PNG. */
  previewKey?: string
}

/**
 * Lógica común de los listados de entidades del admin: filtros + tabs +
 * búsqueda con debounce, modal de alta/edición, y acciones de fila
 * (publicar, papelera, restaurar, borrado definitivo) con confirmación,
 * toasts y manejo de errores. Cada vista pone solo su template.
 */
export function useEntityList<T extends EntityBase>(options: EntityListOptions<T>) {
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

  const status = ref('published')
  const search = ref('')

  const tabs = computed(() => [
    { key: 'published', label: t(`${options.ns}.tabs.published`), icon: CircleCheck },
    { key: 'draft', label: t(`${options.ns}.tabs.draft`), icon: FilePen },
    { key: 'trashed', label: t(`${options.ns}.tabs.trashed`), icon: Trash },
  ])

  /** Valor traducible en el locale activo (con fallback al default). */
  function tr(obj: Translations | null | undefined): string {
    if (!obj) return '—'
    return obj[locales.current] || obj[locales.defaultLocale] || Object.values(obj)[0] || '—'
  }

  /** Slug del locale activo (para URLs de detalle/edición). */
  function slugFor(item: T): string {
    return item.slug?.[locales.current] || Object.values(item.slug || {})[0] || ''
  }

  async function load(page = 1) {
    try {
      await list({ search: search.value, status: status.value, page })
    } catch {
      toast.danger(t('common.errors.load'))
    }
  }

  function reloadPage() {
    load(meta.value?.current_page ?? 1)
  }

  watch(status, () => {
    selectedId.value = null
    load(1)
  })

  let timer: ReturnType<typeof setTimeout> | null = null
  watch(search, () => {
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

  function openCreate() {
    formMode.value = 'create'
    formSlug.value = null
    formOpen.value = true
  }

  function edit(item: T) {
    formMode.value = 'edit'
    formSlug.value = slugFor(item)
    formOpen.value = true
  }

  function goSingle(item: T) {
    router.push({ name: options.singleRoute, params: { slug: slugFor(item) } })
  }

  function onSaved() {
    reloadPage()
  }

  // --- Acciones de fila (con confirmación, toast y errores) ---
  async function togglePublish(item: T) {
    try {
      await action(slugFor(item), 'toggle-published')
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
      await remove(slugFor(item))
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
    status,
    search,
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
