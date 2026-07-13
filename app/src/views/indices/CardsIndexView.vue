<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { FunnelX } from '@lucide/vue'
import {
  BaseButton,
  BasePagination,
  BaseSelect,
  IndexToolbar,
  PreviewGrid,
  useAppRightSidebar,
  type CatalogItem,
  type PreviewGridItem,
} from '@edc-motor/ui'
import { api } from '@/lib/api'
import AddToCollection from '@/components/AddToCollection.vue'
import { useIndexPage } from '@/entities/indexPage'
import { useFiltersQuery } from '@/entities/filtersQuery'
import { parseSort, type SortOption } from '@/entities/catalogSort'

// Índice público de cartas: rejilla de previews sobre GET /api/cards con el
// patrón unificado de los índices (IndexToolbar del motor: búsqueda
// multi-campo con debounce y toggles de orden) y los filtros de juego en la
// barra derecha contextual (AppRightSidebar: registro + Teleport; el botón
// Funnel del header la despliega). Opciones ya localizadas de
// GET /api/cards/filters, aplican en vivo. Facción, tipo, subtipo, dados
// del coste (0..5, 0 = sin coste) y colores (toggles R/G/B con los iconos
// de los dados del gestor) siempre; según los flags del tipo elegido se
// añaden tipo de equipo (is_equipment) y rango/tipo/subtipo de ataque +
// área (allows_subtypes). Todo vive en la query string (useFiltersQuery):
// la UI empuja el estado a la URL y ES el cambio de query el que dispara
// la recarga.
const COLORS = ['R', 'G', 'B'] as const
type CostColor = (typeof COLORS)[number]

// Iconos de los dados por color (url del gestor o null -> punto coloreado).
const COLOR_ICON_KEYS: Record<CostColor, string> = {
  R: 'dice-red',
  G: 'dice-green',
  B: 'dice-blue',
}

interface FilterOption {
  id: number
  name: string
}

interface TypeOption extends FilterOption {
  allows_subtypes: boolean
  is_equipment: boolean
}

const { t } = useI18n()
const { route, router, locales, site, segment, section, canonicalize, applyHead } = useIndexPage()

const items = ref<PreviewGridItem[]>([])
const loading = ref(true)
const page = ref(1)
const pages = ref(0)
const total = ref(0)

// Filtros en la barra derecha contextual: se registra sin título (el
// cascarón pone el suyo, reactivo al locale) y se limpia al salir de la
// vista (el token evita pisar el registro de la vista entrante).
useAppRightSidebar().useRegister()

// Estado de los filtros (los selects usan string: '' = todos).
const search = ref('')
const factionId = ref('')
const typeId = ref('')
const subtypeId = ref('')
const equipmentTypeId = ref('')
const attackRangeId = ref('')
const attackSubtypeId = ref('')
const colors = ref<CostColor[]>([])

// Campos con dominio cerrado: computed con setter que sanea (un valor
// inválido pegado en la URL cae al canónico y el watcher limpia la query).
const diceRaw = ref('')
const dice = computed({
  get: () => diceRaw.value,
  set: (value: string) => {
    diceRaw.value = /^[0-5]$/.test(value) ? value : ''
  },
})

const attackTypeRaw = ref('')
const attackType = computed({
  get: () => attackTypeRaw.value,
  set: (value: string) => {
    attackTypeRaw.value = ['physical', 'magical'].includes(value) ? value : ''
  },
})

const areaRaw = ref('')
const area = computed({
  get: () => areaRaw.value,
  set: (value: string) => {
    areaRaw.value = ['1', '0'].includes(value) ? value : ''
  },
})

const colorsParam = computed({
  get: () => colors.value.join(''),
  set: (value: string) => {
    const upper = value.toUpperCase()
    colors.value = COLORS.filter((color) => upper.includes(color))
  },
})

// Orden: 'latest' es el default del índice (fuera de la URL).
const sortRaw = ref('')
const sort = computed<SortOption>({
  get: () => parseSort(sortRaw.value),
  set: (value) => {
    sortRaw.value = value === 'latest' ? '' : value
  },
})

// Estado <-> query string (URLs compartibles, botón atrás).
const { queryToState, pushQuery } = useFiltersQuery({
  route,
  router,
  search,
  page,
  fields: {
    faction: factionId,
    type: typeId,
    subtype: subtypeId,
    equip: equipmentTypeId,
    range: attackRangeId,
    atk: attackType,
    asub: attackSubtypeId,
    area,
    dice,
    colors: colorsParam,
    sort: sortRaw,
  },
})

// Opciones de los selects (localizadas por el server; se recargan por locale).
const factionOptions = ref<FilterOption[]>([])
const typeOptions = ref<TypeOption[]>([])
const subtypeOptions = ref<FilterOption[]>([])
const equipmentTypeOptions = ref<FilterOption[]>([])
const attackRangeOptions = ref<FilterOption[]>([])
const attackSubtypeOptions = ref<FilterOption[]>([])
const diceIcons = ref<Record<string, string | null>>({})

/** Opciones de BaseSelect con el "todos" delante. */
function withAll(options: FilterOption[], allLabel: string) {
  return [
    { value: '', label: allLabel },
    ...options.map((option) => ({ value: String(option.id), label: option.name })),
  ]
}

const factionSelect = computed(() =>
  withAll(factionOptions.value, t('catalog.filters.allFactions')),
)
const typeSelect = computed(() => withAll(typeOptions.value, t('catalog.filters.allTypes')))
const subtypeSelect = computed(() =>
  withAll(subtypeOptions.value, t('catalog.filters.allSubtypes')),
)
const equipmentTypeSelect = computed(() =>
  withAll(equipmentTypeOptions.value, t('catalog.filters.allEquipmentTypes')),
)
const attackRangeSelect = computed(() =>
  withAll(attackRangeOptions.value, t('catalog.filters.allAttackRanges')),
)
const attackSubtypeSelect = computed(() =>
  withAll(attackSubtypeOptions.value, t('catalog.filters.allAttackSubtypes')),
)

const attackTypeSelect = computed(() => [
  { value: '', label: t('catalog.filters.allAttackTypes') },
  { value: 'physical', label: t('catalog.filters.attackPhysical') },
  { value: 'magical', label: t('catalog.filters.attackMagical') },
])

const areaSelect = computed(() => [
  { value: '', label: t('catalog.filters.areaAll') },
  { value: '1', label: t('catalog.filters.areaYes') },
  { value: '0', label: t('catalog.filters.areaNo') },
])

// Dados del coste: 0 (sin coste) también filtra.
const diceSelect = computed(() => [
  { value: '', label: t('catalog.filters.anyDice') },
  { value: '0', label: t('catalog.filters.noCost') },
  ...[1, 2, 3, 4, 5].map((n) => ({
    value: String(n),
    label: t('singles.deck.dice', { count: n }, n),
  })),
])

// Filtros condicionales según los flags del tipo elegido.
const selectedType = computed(
  () => typeOptions.value.find((option) => String(option.id) === typeId.value) ?? null,
)
const showEquipment = computed(() => !!selectedType.value?.is_equipment)
const showAttack = computed(() => !!selectedType.value?.allows_subtypes)

// Al cambiar de tipo se limpian los condicionales que dejen de aplicar
// (también al cargar las opciones, para sanear URLs pegadas).
watch([selectedType, typeOptions], () => {
  if (!typeOptions.value.length) return
  if (!showEquipment.value) equipmentTypeId.value = ''
  if (!showAttack.value) {
    attackRangeId.value = ''
    attackType.value = ''
    attackSubtypeId.value = ''
    area.value = ''
  }
})

// Nº de filtros activos (enseña el "Quitar filtros" de la barra derecha;
// la búsqueda y el orden no cuentan).
const activeFilters = computed(
  () =>
    [
      factionId.value,
      typeId.value,
      subtypeId.value,
      equipmentTypeId.value,
      attackRangeId.value,
      attackType.value,
      attackSubtypeId.value,
      area.value,
      dice.value,
      colorsParam.value,
    ].filter((value) => value !== '').length,
)

function itemRoute(item: CatalogItem) {
  if (!item.slug || !section.value) return null
  return {
    name: 'entity-detail',
    params: {
      locale: locales.current,
      section: section.value.paths[locales.current] ?? segment.value,
      slug: item.slug,
    },
  }
}

// --- Cargas ---

async function loadFilters() {
  try {
    const { data } = await api.get('/cards/filters')
    const payload = data?.data ?? data ?? {}
    factionOptions.value = Array.isArray(payload.factions) ? payload.factions : []
    typeOptions.value = Array.isArray(payload.types) ? payload.types : []
    subtypeOptions.value = Array.isArray(payload.subtypes) ? payload.subtypes : []
    equipmentTypeOptions.value = Array.isArray(payload.equipment_types)
      ? payload.equipment_types
      : []
    attackRangeOptions.value = Array.isArray(payload.attack_ranges) ? payload.attack_ranges : []
    attackSubtypeOptions.value = Array.isArray(payload.attack_subtypes)
      ? payload.attack_subtypes
      : []
    diceIcons.value = payload.icons ?? {}
  } catch {
    factionOptions.value = []
    typeOptions.value = []
    subtypeOptions.value = []
    equipmentTypeOptions.value = []
    attackRangeOptions.value = []
    attackSubtypeOptions.value = []
    diceIcons.value = {}
  }
}

async function load() {
  if (!section.value || canonicalize()) return
  loading.value = true
  try {
    await site.load() // el head usa documentTitle: sin carreras en el prerender
    const { data } = await api.get('/cards', {
      params: {
        page: page.value,
        search: search.value.trim() || undefined,
        faction_id: factionId.value || undefined,
        card_type_id: typeId.value || undefined,
        card_subtype_id: subtypeId.value || undefined,
        equipment_type_id: equipmentTypeId.value || undefined,
        attack_range_id: attackRangeId.value || undefined,
        attack_type: attackType.value || undefined,
        attack_subtype_id: attackSubtypeId.value || undefined,
        area: area.value || undefined,
        // cost_total admite 0 (cartas sin coste): '' es el único "no filtra".
        cost_total: dice.value === '' ? undefined : dice.value,
        cost_colors: colorsParam.value || undefined,
        sort: sort.value === 'latest' ? undefined : sort.value,
      },
    })
    items.value = (data.data as CatalogItem[]).map((item) => ({ ...item, to: itemRoute(item) }))
    page.value = data.meta.current_page
    pages.value = data.meta.last_page
    total.value = data.meta.total
  } catch {
    items.value = []
    pages.value = 0
    total.value = 0
  } finally {
    loading.value = false
  }
  applyHead(t(section.value.titleKey))
}

// --- Interacciones ---

function toggleColor(color: CostColor) {
  colors.value = colors.value.includes(color)
    ? colors.value.filter((c) => c !== color)
    : COLORS.filter((c) => c === color || colors.value.includes(c))
}

// "Quitar filtros" limpia SOLO los filtros (la búsqueda y el orden quedan).
function clearFilters() {
  factionId.value = ''
  typeId.value = ''
  subtypeId.value = ''
  equipmentTypeId.value = ''
  attackRangeId.value = ''
  attackType.value = ''
  attackSubtypeId.value = ''
  area.value = ''
  dice.value = ''
  colors.value = []
}

function onPage(n: number) {
  page.value = n
  pushQuery(false)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// El cambio de query ES el disparador de la recarga (también el botón atrás
// y las URLs pegadas). El cambio de idioma dispara la canónica (load aborta
// y el nuevo segmento recarga con el locale nuevo).
watch(
  [segment, () => locales.current, () => route.query],
  () => {
    queryToState()
    load()
  },
  { immediate: true },
)

// Opciones de los filtros, por locale.
watch(() => locales.current, loadFilters, { immediate: true })
</script>

<template>
  <main v-if="section" class="catalog-index cards-index">
    <header class="catalog-index__header">
      <h1 class="catalog-index__title">{{ t(section.titleKey) }}</h1>
    </header>

    <IndexToolbar
      v-model="search"
      v-model:sort="sort"
      :search-placeholder="t('catalog.searchPlaceholder')"
      :latest-label="t('catalog.sort.latest')"
      :oldest-label="t('catalog.sort.oldest')"
      :name-label="t('catalog.sort.nameAsc')"
      :name-desc-label="t('catalog.sort.nameDesc')"
    />

    <!-- Filtros en la barra derecha contextual (aplican en vivo) -->
    <Teleport defer to="#app-right-sidebar-target">
      <BaseSelect
        v-model="factionId"
        :label="t('catalog.filters.faction')"
        :options="factionSelect"
      />
      <BaseSelect v-model="typeId" :label="t('catalog.filters.type')" :options="typeSelect" />
      <BaseSelect
        v-model="subtypeId"
        :label="t('catalog.filters.subtype')"
        :options="subtypeSelect"
      />

      <!-- Condicionales del tipo elegido (flags del endpoint de filtros) -->
      <BaseSelect
        v-if="showEquipment"
        v-model="equipmentTypeId"
        :label="t('catalog.filters.equipmentType')"
        :options="equipmentTypeSelect"
      />
      <template v-if="showAttack">
        <!-- Orden canónico: rango · tipo · subtipo · área -->
        <BaseSelect
          v-model="attackRangeId"
          :label="t('catalog.filters.attackRange')"
          :options="attackRangeSelect"
        />
        <BaseSelect
          v-model="attackType"
          :label="t('catalog.filters.attackType')"
          :options="attackTypeSelect"
        />
        <BaseSelect
          v-model="attackSubtypeId"
          :label="t('catalog.filters.attackSubtype')"
          :options="attackSubtypeSelect"
        />
        <BaseSelect v-model="area" :label="t('catalog.filters.area')" :options="areaSelect" />
      </template>

      <BaseSelect v-model="dice" :label="t('catalog.filters.dice')" :options="diceSelect" />

      <!-- Colores del coste: toggles con el icono del dado (o punto de color) -->
      <div class="form-field cost-colors">
        <span class="form-field__label">{{ t('catalog.filters.colors') }}</span>
        <div class="cost-colors__group" role="group" :aria-label="t('catalog.filters.colors')">
          <button
            v-for="color in COLORS"
            :key="color"
            type="button"
            class="cost-colors__toggle"
            :class="[
              `cost-colors__toggle--${color.toLowerCase()}`,
              { 'is-active': colors.includes(color) },
            ]"
            :aria-pressed="colors.includes(color)"
            :aria-label="t(`catalog.filters.color${color}`)"
            :title="t(`catalog.filters.color${color}`)"
            @click="toggleColor(color)"
          >
            <img
              v-if="diceIcons[COLOR_ICON_KEYS[color]]"
              :src="diceIcons[COLOR_ICON_KEYS[color]] ?? undefined"
              alt=""
              class="cost-colors__icon"
            />
            <span v-else class="cost-colors__dot" aria-hidden="true"></span>
          </button>
        </div>
      </div>

      <!-- "Quitar filtros" (solo con filtros activos), como el pie del
           antiguo modal: la búsqueda y el orden se quedan como están -->
      <BaseButton v-if="activeFilters > 0" variant="secondary" type="button" @click="clearFilters">
        <template #icon><FunnelX :size="16" /></template>
        {{ t('catalog.filters.clear') }}
      </BaseButton>
    </Teleport>

    <BasePagination
      class="catalog-index__pagination"
      :page="page"
      :pages="pages"
      :prev-label="t('catalog.pagination.prev')"
      :next-label="t('catalog.pagination.next')"
      :of-label="t('catalog.pagination.of', { page, pages })"
      @update:page="onPage"
    />

    <p v-if="!loading && items.length" class="catalog-index__count">
      {{ t('catalog.results', { count: total }, total) }}
    </p>

    <p v-if="loading && !items.length" class="catalog-index__loading" role="status">
      {{ t('catalog.loading') }}
    </p>
    <PreviewGrid
      v-else
      :items="items"
      :loading="loading"
      :page="page"
      :pages="pages"
      :empty-text="t('catalog.empty')"
      :prev-label="t('catalog.prev')"
      :next-label="t('catalog.next')"
      @page="onPage"
    >
      <!-- Añadir a la colección "para imprimir", flotante sobre la carta -->
      <template v-if="section.collectible" #actions="{ item }">
        <AddToCollection :id="item.id" class="catalog-index__add" :entity="section.collectible" />
      </template>
    </PreviewGrid>
  </main>
</template>
