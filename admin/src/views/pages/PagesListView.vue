<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import {
  ArrowLeft,
  ArrowRight,
  Eye,
  GripVertical,
  House as HomeIcon,
  Plus,
  Printer,
  SquarePen,
  Trash2,
} from '@lucide/vue'
import { BaseButton, BaseSelect, useConfirm, useToast } from '@edc-motor/ui'
import { useCardDeselect, useRightSidebar } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import ListToolbar from '@/components/ListToolbar.vue'
import PageFormModal, { type PageRow } from '@/components/pages/PageFormModal.vue'
import { useLocalesStore } from '@/stores/locales'

// Listado de páginas del CRM. TODA la tarjeta selecciona (salvo controles):
// el panel derecho trae las acciones (patrón kontuan, arriba del todo) y las
// rápidas sin modal (publicar, imprimible, home). El título y la flecha
// entran al single (bloques). Los filtros del listado viven en el propio
// panel derecho: sin card seleccionada se muestran los selects; con
// selección, el botón "volver a los filtros" deselecciona (también un click
// en la zona vacía del contenido). Tarjetas arrastrables (drag & drop
// nativo, un solo nivel — es la misma jerarquía que deriva el menú): soltar
// entre cards reordena (persiste al momento, `POST /admin/pages/reorder`);
// soltar ENCIMA de una card RAÍZ la convierte en madre de la arrastrada
// (`PUT /admin/pages/{id}`, misma validación que el resto del CRM); soltar
// en el hueco entre cards raíz saca una hija a la raíz (única forma: el
// panel ya no repite esa acción, es un caso particular del propio arrastre).
const { t, te } = useI18n()
const router = useRouter()
const toast = useToast()
const { confirm } = useConfirm()
const locales = useLocalesStore()

const sidebar = useRightSidebar()
sidebar.useRegister(t('pages.panelTitle'))

const pages = ref<PageRow[]>([])
const loading = ref(true)
const formOpen = ref(false)
const editing = ref<PageRow | null>(null)
const selectedId = ref<number | null>(null)

// Búsqueda + filtro de estado (en el panel derecho). El árbol se ordena en
// el servidor (madre → hijas), así que el toolbar va sin toggles de orden.
const search = ref('')
const statusFilter = ref('')

const statusOptions = computed(() => [
  { value: '', label: t('pages.filters.all') },
  { value: 'published', label: t('pages.filters.published') },
  { value: 'draft', label: t('pages.filters.draft') },
])

const selected = computed(() => pages.value.find((p) => p.id === selectedId.value) ?? null)

/** Texto en el locale actual del admin, con fallback al primer valor no vacío. */
function displayText(map: Record<string, string> | null | undefined): string {
  if (!map) return ''
  return map[locales.current] || Object.values(map).find(Boolean) || ''
}

function pageTitle(page: PageRow): string {
  return displayText(page.title)
}

/** Etiqueta de la madre de una página hija (indicador de jerarquía). */
function parentTitle(page: PageRow): string {
  const parent = pages.value.find((p) => p.id === page.parent_id)
  return parent ? pageTitle(parent) : ''
}

// Bloques de la página seleccionada (solo tipo + resumen de una línea).
interface BlockTypeInfo {
  key: string
  name: string
  fields: { key: string; type: string; translatable: boolean }[]
}
const blockTypes = ref<BlockTypeInfo[]>([])
const selectedBlocks = ref<{ id: number; type: string; settings: Record<string, unknown> }[]>([])

function blockTypeName(key: string): string {
  const fallback = blockTypes.value.find((t) => t.key === key)?.name ?? key
  return te(`blockTypes.${key}`) ? t(`blockTypes.${key}`) : fallback
}

/** Primer texto traducible con valor, sin HTML (una línea en el panel). */
function blockSummary(block: { type: string; settings: Record<string, unknown> }): string {
  const type = blockTypes.value.find((t) => t.key === block.type)
  for (const field of type?.fields ?? []) {
    if (!['text', 'textarea', 'richtext'].includes(field.type)) continue
    const value = block.settings?.[field.key]
    if (field.translatable && value && typeof value === 'object') {
      const text = displayText(value as Record<string, string>)
      if (text) return text.replace(/<[^>]*>/g, '').slice(0, 90)
    }
  }
  return ''
}

watch(selectedId, async (id) => {
  selectedBlocks.value = []
  if (!id) return
  try {
    if (!blockTypes.value.length) {
      const { data } = await api.get('/admin/block-types')
      blockTypes.value = data.data
    }
    const { data } = await api.get(`/admin/pages/${id}/blocks`)
    selectedBlocks.value = data.data
  } catch {
    // la sección de bloques del panel es informativa: sin toast
  }
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/pages', {
      params: { search: search.value, status: statusFilter.value || undefined },
    })
    pages.value = data.data
  } catch {
    toast.danger(t('common.errors.load'))
  } finally {
    loading.value = false
  }
}

// Búsqueda y filtro comparten debounce (mismo ritmo que useEntityList).
let timer: ReturnType<typeof setTimeout> | null = null
watch([search, statusFilter], () => {
  if (timer) clearTimeout(timer)
  timer = setTimeout(load, 250)
})
onBeforeUnmount(() => {
  if (timer) clearTimeout(timer)
})

/** Toda la tarjeta selecciona, salvo sus controles interiores y el asa. */
function select(page: PageRow, event: MouseEvent) {
  const target = event.target as HTMLElement | null
  if (target?.closest('button, a, input, label, .pages-view__grip')) return
  selectedId.value = page.id
  sidebar.reveal()
}

// Click en la zona vacía del contenido (fuera de una card o control):
// deselecciona y el panel vuelve a los filtros.
useCardDeselect(() => (selectedId.value = null), '.pages-view__item')

function open(page: PageRow) {
  router.push({ name: 'page', params: { id: page.id } })
}

function openCreate() {
  editing.value = null
  formOpen.value = true
}

function openEdit(page: PageRow) {
  editing.value = page
  formOpen.value = true
}

/** Acción rápida del panel: alterna un flag sin abrir el modal. */
async function toggleFlag(flag: 'is_published' | 'is_printable', value: boolean) {
  if (!selected.value) return
  try {
    await api.put(`/admin/pages/${selected.value.id}`, { [flag]: value })
    selected.value[flag] = value
    toast.success(t('pages.toast.saved'))
  } catch {
    toast.danger(t('common.errors.action'))
  }
}

// --- Drag & drop nativo (HTML5, sin dependencias) --------------------------
// Un solo nivel (misma jerarquía que deriva el menú): soltar en el tercio
// superior/inferior de otra card reordena como hermana; soltar en el
// tercio central de una card RAÍZ la convierte en madre de la arrastrada
// (si es válida: una página CON hijas no puede meterse dentro de otra, y
// solo se anida bajo páginas raíz). Arrastrar una hija hasta el hueco entre
// cards raíz la saca a la raíz.
const dragged = ref<PageRow | null>(null)
const dropTarget = ref<{ id: number; position: 'before' | 'after' | 'inside' } | null>(null)
const dragging = ref(false)

function hasChildren(id: number): boolean {
  return pages.value.some((p) => p.parent_id === id)
}

function canNestInside(target: PageRow): boolean {
  if (!dragged.value) return false
  if (target.parent_id || target.id === dragged.value.id) return false
  return !hasChildren(dragged.value.id)
}

function canPlaceSibling(target: PageRow): boolean {
  if (!dragged.value) return false
  if (target.id === dragged.value.id) return false
  if (target.parent_id && hasChildren(dragged.value.id)) return false
  return true
}

function onDragStart(page: PageRow, event: DragEvent) {
  dragged.value = page
  event.dataTransfer?.setData('text/plain', String(page.id))
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

function onDragOver(page: PageRow, event: DragEvent) {
  if (!dragged.value || dragged.value.id === page.id) {
    dropTarget.value = null
    return
  }
  const rect = (event.currentTarget as HTMLElement).getBoundingClientRect()
  const ratio = (event.clientY - rect.top) / rect.height
  const nestable = canNestInside(page)

  let position: 'before' | 'after' | 'inside'
  if (nestable && ratio > 0.3 && ratio < 0.7) {
    position = 'inside'
  } else {
    if (!canPlaceSibling(page)) {
      dropTarget.value = null
      return
    }
    position = ratio < 0.5 ? 'before' : 'after'
  }
  dropTarget.value = { id: page.id, position }
}

function onDragLeave(page: PageRow, event: DragEvent) {
  const related = event.relatedTarget as Node | null
  const current = event.currentTarget as HTMLElement
  if (related && current.contains(related)) return
  if (dropTarget.value?.id === page.id) dropTarget.value = null
}

function onDragEnd() {
  dragged.value = null
  dropTarget.value = null
}

async function onDrop(page: PageRow, event: DragEvent) {
  event.preventDefault()
  const target = dropTarget.value
  const source = dragged.value
  dragged.value = null
  dropTarget.value = null
  if (!target || !source || target.id !== page.id || dragging.value) return

  const oldParentId = source.parent_id
  const newParentId =
    target.position === 'inside'
      ? target.id
      : (pages.value.find((p) => p.id === target.id)?.parent_id ?? null)

  // Hermanas del padre DESTINO (sin la arrastrada), con ella insertada en
  // su nueva posición: es la lista completa que persiste el reorder.
  const siblings = pages.value.filter((p) => p.parent_id === newParentId && p.id !== source.id)
  let insertAt = siblings.length
  if (target.position !== 'inside') {
    const idx = siblings.findIndex((p) => p.id === target.id)
    insertAt = idx < 0 ? siblings.length : target.position === 'after' ? idx + 1 : idx
  }
  siblings.splice(insertAt, 0, source)

  dragging.value = true
  try {
    if (newParentId !== oldParentId) {
      await api.put(`/admin/pages/${source.id}`, { parent_id: newParentId })
    }
    await api.post('/admin/pages/reorder', { ids: siblings.map((p) => p.id) })
    await load()
  } catch {
    toast.danger(t('common.errors.action'))
    await load()
  } finally {
    dragging.value = false
  }
}

async function setHome(page: PageRow) {
  try {
    await api.post(`/admin/pages/${page.id}/set-home`)
    toast.success(t('pages.toast.homeSet'))
    await load()
  } catch {
    toast.danger(t('common.errors.action'))
  }
}

async function remove(page: PageRow) {
  const ok = await confirm({
    message: t('pages.confirmDelete', { name: pageTitle(page) }),
    confirmLabel: t('common.actions.delete'),
    cancelLabel: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await api.delete(`/admin/pages/${page.id}`)
    if (selectedId.value === page.id) selectedId.value = null
    toast.success(t('pages.toast.deleted'))
    await load()
  } catch {
    toast.danger(t('common.errors.action'))
  }
}

onMounted(load)
</script>

<template>
  <div class="pages-view">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('pages.new') }}
      </BaseButton>
    </div>

    <!-- Barra del índice: solo búsqueda (el árbol no se reordena; los
         filtros viven en el panel derecho) -->
    <ListToolbar v-model="search" :show-sort="false" />

    <p v-if="!loading && !pages.length" class="pages-view__empty">{{ t('common.empty') }}</p>

    <div class="pages-view__list">
      <article
        v-for="page in pages"
        :key="page.id"
        class="pages-view__item"
        draggable="true"
        :class="{
          'is-child': page.parent_id,
          'is-active': selectedId === page.id,
          'is-dragging': dragged?.id === page.id,
          'drop-before': dropTarget?.id === page.id && dropTarget.position === 'before',
          'drop-after': dropTarget?.id === page.id && dropTarget.position === 'after',
          'drop-inside': dropTarget?.id === page.id && dropTarget.position === 'inside',
        }"
        @click="(e) => select(page, e)"
        @dragstart="onDragStart(page, $event)"
        @dragover.prevent="onDragOver(page, $event)"
        @dragleave="onDragLeave(page, $event)"
        @drop="onDrop(page, $event)"
        @dragend="onDragEnd"
      >
        <span class="pages-view__grip"><GripVertical :size="16" /></span>
        <button type="button" class="pages-view__title" @click="open(page)">
          {{ pageTitle(page) }}
        </button>
        <span class="pages-view__slug">
          /{{ page.slug[locales.current] ?? page.slug.es ?? '' }}
          <em v-if="page.parent_id" class="pages-view__parent">
            {{ t('pages.childOf', { name: parentTitle(page) }) }}
          </em>
        </span>
        <span class="pages-view__chips">
          <span v-if="page.is_home" class="chip is-ok">{{ t('pages.homeChip') }}</span>
          <span :class="['chip', page.is_published ? 'is-ok' : 'is-missing']">
            {{ page.is_published ? t('pages.published') : t('pages.draft') }}
          </span>
          <span v-if="page.is_printable" class="chip is-info">{{ t('pages.printable') }}</span>
          <span class="chip">{{ page.blocks_count ?? 0 }} ▤</span>
        </span>
        <span class="pages-view__buttons">
          <button type="button" class="card-enter" @click="open(page)">
            {{ t('common.actions.enter') }} <ArrowRight :size="14" />
          </button>
        </span>
      </article>
    </div>

    <PageFormModal v-model="formOpen" :page="editing" :pages="pages" @saved="load" />

    <!-- Panel derecho: sin selección, los filtros del listado (aplican en
         vivo); con selección, "volver a los filtros" + las acciones -->
    <Teleport defer to="#right-sidebar-target">
      <div class="manager-panel">
        <template v-if="!selected">
          <p class="manager-panel__empty">{{ t('pages.panelEmpty') }}</p>

          <hr class="manager-panel__divider" />

          <p class="manager-panel__kicker">{{ t('common.filters') }}</p>
          <BaseSelect
            v-model="statusFilter"
            :label="t('pages.filters.status')"
            :options="statusOptions"
          />
        </template>
        <template v-else>
          <button type="button" class="manager-panel__back" @click="selectedId = null">
            <ArrowLeft :size="14" />
            {{ t('common.backToFilters') }}
          </button>

          <hr class="manager-panel__divider" />

          <p class="manager-panel__kicker">{{ t('pages.panelTitle') }}</p>

          <!-- Acciones de verdad (patrón panel): los interruptores de
               estado van en su propia sección, debajo -->
          <div class="manager-detail__actions">
            <BaseButton @click="open(selected)">
              <template #icon><ArrowRight :size="14" /></template>
              {{ t('pages.open') }}
            </BaseButton>
            <BaseButton variant="info" @click="openEdit(selected)">
              <template #icon><SquarePen :size="14" /></template>
              {{ t('common.actions.edit') }}
            </BaseButton>
            <BaseButton v-if="!selected.is_home" variant="warning" @click="setHome(selected)">
              <template #icon><HomeIcon :size="14" /></template>
              {{ t('pages.setHome') }}
            </BaseButton>
            <BaseButton variant="danger" @click="remove(selected)">
              <template #icon><Trash2 :size="14" /></template>
              {{ t('common.actions.delete') }}
            </BaseButton>
          </div>

          <!-- Estado: los interruptores (flags), separados de las
               acciones de verdad -->
          <hr class="manager-panel__divider" />
          <p class="manager-panel__kicker">{{ t('common.stateKicker') }}</p>
          <div class="manager-detail__actions">
            <BaseButton
              variant="success"
              :class="selected.is_published ? 'is-on' : 'is-off'"
              :aria-pressed="selected.is_published"
              @click="toggleFlag('is_published', !selected.is_published)"
            >
              <template #icon><Eye :size="14" /></template>
              {{ t('pages.published') }}
            </BaseButton>
            <BaseButton
              variant="info"
              :class="selected.is_printable ? 'is-on' : 'is-off'"
              :aria-pressed="selected.is_printable"
              @click="toggleFlag('is_printable', !selected.is_printable)"
            >
              <template #icon><Printer :size="14" /></template>
              {{ t('pages.printable') }}
            </BaseButton>
          </div>

          <hr class="manager-panel__divider" />

          <h3 class="manager-detail__title">
            {{ pageTitle(selected) }}
          </h3>

          <!-- Info: slugs por idioma -->
          <p v-for="(slugValue, code) in selected.slug" :key="code" class="manager-detail__meta">
            <strong>{{ String(code).toUpperCase() }}</strong> /{{ slugValue }}
          </p>

          <!-- Sus bloques: tipo + resumen de una línea -->
          <hr v-if="selectedBlocks.length" class="manager-panel__divider" />
          <div v-if="selectedBlocks.length" class="manager-detail">
            <p class="manager-panel__kicker">{{ t('pages.panelBlocks') }}</p>
            <ul class="manager-detail__rows">
              <li v-for="block in selectedBlocks" :key="block.id" class="manager-detail__row-line">
                <strong>{{ blockTypeName(block.type) }}</strong>
                <span v-if="blockSummary(block)" class="manager-detail__row-text">{{
                  blockSummary(block)
                }}</span>
              </li>
            </ul>
          </div>
        </template>
      </div>
    </Teleport>
  </div>
</template>
