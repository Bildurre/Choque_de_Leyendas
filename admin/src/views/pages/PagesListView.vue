<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import {
  ArrowLeft,
  ArrowRight,
  Eye,
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

// Listado de páginas del CRM. TODA la tarjeta selecciona (salvo controles):
// el panel derecho trae las acciones (patrón kontuan, arriba del todo) y las
// rápidas sin modal (publicar, imprimible, home). El título y la flecha
// entran al single (bloques). Los filtros del listado viven en el propio
// panel derecho: sin card seleccionada se muestran los selects; con
// selección, el botón "volver a los filtros" deselecciona (también un click
// en la zona vacía del contenido).
const { t, te } = useI18n()
const router = useRouter()
const toast = useToast()
const { confirm } = useConfirm()

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
      const text = Object.values(value as Record<string, string>).find(Boolean)
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

/** Toda la tarjeta selecciona, salvo sus controles interiores. */
function select(page: PageRow, event: MouseEvent) {
  const target = event.target as HTMLElement | null
  if (target?.closest('button, a, input, label')) return
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
    message: t('pages.confirmDelete', { name: page.title.es ?? '' }),
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
        :class="{ 'is-child': page.parent_id, 'is-active': selectedId === page.id }"
        @click="(e) => select(page, e)"
      >
        <button type="button" class="pages-view__title" @click="open(page)">
          {{ page.title.es ?? Object.values(page.title)[0] }}
        </button>
        <span class="pages-view__slug">/{{ page.slug.es ?? '' }}</span>
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

          <!-- Acciones PRIMERO (los interruptores publicada/imprimible
               arriba); después, secciones separadas (patrón panel) -->
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

          <hr class="manager-panel__divider" />

          <h3 class="manager-detail__title">
            {{ selected.title.es ?? Object.values(selected.title)[0] }}
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
