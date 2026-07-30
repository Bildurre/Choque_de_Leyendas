<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { FunnelX } from '@lucide/vue'
import {
  BaseButton,
  BasePagination,
  IndexToolbar,
  MultiSelect,
  PreviewGrid,
  type CatalogItem,
  type PreviewGridItem,
} from '@edc-motor/ui'
import { api } from '@/lib/api'
import AddToCollection from '@/components/AddToCollection.vue'
import CatalogFilters from '@/components/indices/CatalogFilters.vue'
import type { EntitySection } from '@/entities/registry'
import { csvField, useFiltersQuery } from '@/entities/filtersQuery'
import { parseSort, type SortOption } from '@/entities/catalogSort'
import { useLocalesStore } from '@/stores/locales'

// Catálogo público de cartas EXTRAÍDO de la vista índice (que queda como
// cáscara: canónica + SEO + IndexHeader) para que también lo monte el bloque
// «Índice de entidad» del CRM: rejilla de previews sobre GET /api/cards con
// el patrón unificado de los índices (IndexToolbar del motor: búsqueda
// multi-campo con debounce y toggles de orden) y los filtros de juego en
// CatalogFilters (en ancho, panel plegable bajo la búsqueda; en estrecho,
// la barra derecha off-canvas del motor). Opciones ya localizadas de
// GET /api/cards/filters, aplican en vivo y son MULTISELECT: cada filtro
// admite varios valores (unión; la API filtra con whereIn y recibe
// `clave[]=`). Facción, tipo, subtipo, dados del coste (0..5, 0 = sin
// coste) y colores (toggles R/G/B con los iconos de los dados del gestor)
// siempre; según los flags de los tipos elegidos se añaden tipo y subtipo
// de equipo (is_equipment) y rango/tipo/subtipo de ataque + área
// (allows_subtypes). Todo vive en la query string (useFiltersQuery, listas
// separadas por comas): la UI empuja el estado a la URL y ES el cambio de
// query el que dispara la recarga.
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

interface EquipmentSubtypeOption extends FilterOption {
  equipment_type_id: number
}

const props = defineProps<{ section: EntitySection }>()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const locales = useLocalesStore()

const items = ref<PreviewGridItem[]>([])
const loading = ref(true)
const page = ref(1)
const pages = ref(0)
const total = ref(0)

// Estado de los filtros: arrays de strings ([] = todos), lo que edita cada
// MultiSelect. En la URL viajan como listas separadas por comas (csvField),
// con saneo en los dominios cerrados (un valor inválido pegado en la URL
// se descarta y el watcher limpia la query).
const search = ref('')
const factionIds = ref<string[]>([])
const typeIds = ref<string[]>([])
const subtypeIds = ref<string[]>([])
const equipmentTypeIds = ref<string[]>([])
const equipmentSubtypeIds = ref<string[]>([])
const attackRangeIds = ref<string[]>([])
const attackSubtypeIds = ref<string[]>([])
const attackTypes = ref<string[]>([])
const areas = ref<string[]>([])
const dice = ref<string[]>([])
const colors = ref<CostColor[]>([])

const colorsParam = computed({
  get: () => colors.value.join(''),
  set: (value: string) => {
    const upper = value.toUpperCase()
    colors.value = COLORS.filter((color) => upper.includes(color))
  },
})

// Orden: 'name' (alfabético del locale activo) es el default del índice
// (fuera de la URL).
const sortRaw = ref('')
const sort = computed<SortOption>({
  get: () => parseSort(sortRaw.value),
  set: (value) => {
    sortRaw.value = value === 'name' ? '' : value
  },
})

// Estado <-> query string (URLs compartibles, botón atrás).
const { queryToState, pushQuery } = useFiltersQuery({
  route,
  router,
  search,
  page,
  fields: {
    faction: csvField(factionIds),
    type: csvField(typeIds),
    subtype: csvField(subtypeIds),
    equip: csvField(equipmentTypeIds),
    esub: csvField(equipmentSubtypeIds),
    range: csvField(attackRangeIds),
    atk: csvField(attackTypes, (value) => ['physical', 'magical'].includes(value)),
    asub: csvField(attackSubtypeIds),
    area: csvField(areas, (value) => ['1', '0'].includes(value)),
    dice: csvField(dice, (value) => /^[0-5]$/.test(value)),
    colors: colorsParam,
    sort: sortRaw,
  },
})

// Opciones de los selects (localizadas por el server; se recargan por locale).
const factionOptions = ref<FilterOption[]>([])
const typeOptions = ref<TypeOption[]>([])
const subtypeOptions = ref<FilterOption[]>([])
const equipmentTypeOptions = ref<FilterOption[]>([])
const equipmentSubtypeOptions = ref<EquipmentSubtypeOption[]>([])
const attackRangeOptions = ref<FilterOption[]>([])
const attackSubtypeOptions = ref<FilterOption[]>([])
const diceIcons = ref<Record<string, string | null>>({})

// Opciones de MultiSelect: sin opción "todos" en la lista (sin nada
// marcado, el placeholder ya dice "Todos los …").
function toSelect(options: FilterOption[]) {
  return options.map((option) => ({ value: String(option.id), label: option.name }))
}

const factionSelect = computed(() => toSelect(factionOptions.value))
const typeSelect = computed(() => toSelect(typeOptions.value))
const subtypeSelect = computed(() => toSelect(subtypeOptions.value))
const equipmentTypeSelect = computed(() => toSelect(equipmentTypeOptions.value))
// Subtipos de equipo acotados a los tipos de equipo marcados (todos si no hay).
const equipmentSubtypeSelect = computed(() =>
  toSelect(
    equipmentSubtypeOptions.value.filter(
      (option) =>
        !equipmentTypeIds.value.length ||
        equipmentTypeIds.value.includes(String(option.equipment_type_id)),
    ),
  ),
)
const attackRangeSelect = computed(() => toSelect(attackRangeOptions.value))
const attackSubtypeSelect = computed(() => toSelect(attackSubtypeOptions.value))

const attackTypeSelect = computed(() => [
  { value: 'physical', label: t('catalog.filters.attackPhysical') },
  { value: 'magical', label: t('catalog.filters.attackMagical') },
])

const areaSelect = computed(() => [
  { value: '1', label: t('catalog.filters.areaYes') },
  { value: '0', label: t('catalog.filters.areaNo') },
])

// Dados del coste: 0 (sin coste) también filtra.
const diceSelect = computed(() => [
  { value: '0', label: t('catalog.filters.noCost') },
  ...[1, 2, 3, 4, 5].map((n) => ({
    value: String(n),
    label: t('singles.deck.dice', { count: n }, n),
  })),
])

// Filtros condicionales según los flags de los tipos marcados (basta con
// que UN tipo marcado los aplique).
const selectedTypes = computed(() =>
  typeOptions.value.filter((option) => typeIds.value.includes(String(option.id))),
)
const showEquipment = computed(() => selectedTypes.value.some((option) => option.is_equipment))
const showAttack = computed(() => selectedTypes.value.some((option) => option.allows_subtypes))

// Al cambiar los tipos se limpian los condicionales que dejen de aplicar
// (también al cargar las opciones, para sanear URLs pegadas).
watch([selectedTypes, typeOptions], () => {
  if (!typeOptions.value.length) return
  if (!showEquipment.value) {
    if (equipmentTypeIds.value.length) equipmentTypeIds.value = []
    if (equipmentSubtypeIds.value.length) equipmentSubtypeIds.value = []
  }
  if (!showAttack.value) {
    if (attackRangeIds.value.length) attackRangeIds.value = []
    if (attackTypes.value.length) attackTypes.value = []
    if (attackSubtypeIds.value.length) attackSubtypeIds.value = []
    if (areas.value.length) areas.value = []
  }
})

// Al cambiar los tipos de equipo se limpian los subtipos marcados que no
// pertenezcan a ninguno (los demás se quedan).
watch([equipmentTypeIds, equipmentSubtypeOptions], () => {
  if (!equipmentSubtypeIds.value.length || !equipmentSubtypeOptions.value.length) return
  const valid = equipmentSubtypeIds.value.filter((id) =>
    equipmentSubtypeOptions.value.some(
      (option) =>
        String(option.id) === id &&
        (!equipmentTypeIds.value.length ||
          equipmentTypeIds.value.includes(String(option.equipment_type_id))),
    ),
  )
  if (valid.length !== equipmentSubtypeIds.value.length) equipmentSubtypeIds.value = valid
})

// Nº de filtros activos (badge del botón «Filtros» y visibilidad del
// "Quitar filtros"; la búsqueda y el orden no cuentan).
const activeFilters = computed(
  () =>
    [
      factionIds.value,
      typeIds.value,
      subtypeIds.value,
      equipmentTypeIds.value,
      equipmentSubtypeIds.value,
      attackRangeIds.value,
      attackTypes.value,
      attackSubtypeIds.value,
      areas.value,
      dice.value,
      colors.value,
    ].filter((values) => values.length > 0).length,
)

function itemRoute(item: CatalogItem) {
  const segment = props.section.paths[locales.current] ?? Object.values(props.section.paths)[0]
  if (!item.slug || !segment) return null
  return {
    name: 'entity-detail',
    params: { locale: locales.current, section: segment, slug: item.slug },
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
    equipmentSubtypeOptions.value = Array.isArray(payload.equipment_subtypes)
      ? payload.equipment_subtypes
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
    equipmentSubtypeOptions.value = []
    attackRangeOptions.value = []
    attackSubtypeOptions.value = []
    diceIcons.value = {}
  }
}

async function load() {
  loading.value = true
  try {
    // Cada filtro viaja como array (`clave[]=`, serialización de axios);
    // vacío = no viaja (no filtra).
    const listParam = (values: string[]) => (values.length ? values : undefined)
    const { data } = await api.get('/cards', {
      params: {
        page: page.value,
        search: search.value.trim() || undefined,
        faction_id: listParam(factionIds.value),
        card_type_id: listParam(typeIds.value),
        card_subtype_id: listParam(subtypeIds.value),
        equipment_type_id: listParam(equipmentTypeIds.value),
        equipment_subtype_id: listParam(equipmentSubtypeIds.value),
        attack_range_id: listParam(attackRangeIds.value),
        attack_type: listParam(attackTypes.value),
        attack_subtype_id: listParam(attackSubtypeIds.value),
        area: listParam(areas.value),
        cost_total: listParam(dice.value),
        cost_colors: colorsParam.value || undefined,
        sort: sort.value === 'name' ? undefined : sort.value,
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
}

// --- Interacciones ---

function toggleColor(color: CostColor) {
  colors.value = colors.value.includes(color)
    ? colors.value.filter((c) => c !== color)
    : COLORS.filter((c) => c === color || colors.value.includes(c))
}

// "Quitar filtros" limpia SOLO los filtros (la búsqueda y el orden quedan).
function clearFilters() {
  factionIds.value = []
  typeIds.value = []
  subtypeIds.value = []
  equipmentTypeIds.value = []
  equipmentSubtypeIds.value = []
  attackRangeIds.value = []
  attackTypes.value = []
  attackSubtypeIds.value = []
  areas.value = []
  dice.value = []
  colors.value = []
}

function onPage(n: number) {
  page.value = n
  pushQuery(false)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// El cambio de query ES el disparador de la recarga (también el botón atrás
// y las URLs pegadas); el cambio de idioma recarga con el locale nuevo (la
// cáscara que monta este catálogo se ocupa de la URL canónica).
watch(
  [() => locales.current, () => route.query],
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
  <IndexToolbar
    v-model="search"
    v-model:sort="sort"
    :search-placeholder="t('catalog.searchPlaceholder')"
    :latest-label="t('catalog.sort.latest')"
    :oldest-label="t('catalog.sort.oldest')"
    :name-label="t('catalog.sort.nameAsc')"
    :name-desc-label="t('catalog.sort.nameDesc')"
  />

  <!-- Filtros bajo la búsqueda en ancho (panel plegable); en estrecho, en
       la barra derecha off-canvas del motor. Aplican en vivo, multivalor -->
  <CatalogFilters :active-count="activeFilters">
    <MultiSelect
      v-model="factionIds"
      :label="t('catalog.filters.faction')"
      :placeholder="t('catalog.filters.allFactions')"
      :options="factionSelect"
    />
    <MultiSelect
      v-model="typeIds"
      :label="t('catalog.filters.type')"
      :placeholder="t('catalog.filters.allTypes')"
      :options="typeSelect"
    />
    <MultiSelect
      v-model="subtypeIds"
      :label="t('catalog.filters.subtype')"
      :placeholder="t('catalog.filters.allSubtypes')"
      :options="subtypeSelect"
    />

    <!-- Condicionales de los tipos elegidos (flags del endpoint de filtros) -->
    <template v-if="showEquipment">
      <MultiSelect
        v-model="equipmentTypeIds"
        :label="t('catalog.filters.equipmentType')"
        :placeholder="t('catalog.filters.allEquipmentTypes')"
        :options="equipmentTypeSelect"
      />
      <MultiSelect
        v-model="equipmentSubtypeIds"
        :label="t('catalog.filters.equipmentSubtype')"
        :placeholder="t('catalog.filters.allEquipmentSubtypes')"
        :options="equipmentSubtypeSelect"
      />
    </template>
    <template v-if="showAttack">
      <!-- Orden canónico: rango · tipo · subtipo · área -->
      <MultiSelect
        v-model="attackRangeIds"
        :label="t('catalog.filters.attackRange')"
        :placeholder="t('catalog.filters.allAttackRanges')"
        :options="attackRangeSelect"
      />
      <MultiSelect
        v-model="attackTypes"
        :label="t('catalog.filters.attackType')"
        :placeholder="t('catalog.filters.allAttackTypes')"
        :options="attackTypeSelect"
      />
      <MultiSelect
        v-model="attackSubtypeIds"
        :label="t('catalog.filters.attackSubtype')"
        :placeholder="t('catalog.filters.allAttackSubtypes')"
        :options="attackSubtypeSelect"
      />
      <MultiSelect
        v-model="areas"
        :label="t('catalog.filters.area')"
        :placeholder="t('catalog.filters.areaAll')"
        :options="areaSelect"
      />
    </template>

    <MultiSelect
      v-model="dice"
      :label="t('catalog.filters.dice')"
      :placeholder="t('catalog.filters.anyDice')"
      :options="diceSelect"
    />

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
  </CatalogFilters>

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
  <PreviewGrid v-else :items="items" :loading="loading" :empty-text="t('catalog.empty')">
    <!-- Añadir a la colección "para imprimir", flotante sobre la carta -->
    <template v-if="section.collectible" #actions="{ item }">
      <AddToCollection :id="item.id" class="catalog-index__add" :entity="section.collectible" />
    </template>
  </PreviewGrid>

  <!-- Paginación inferior: gemela de la superior (mismas clases/props) -->
  <BasePagination
    class="catalog-index__pagination"
    :page="page"
    :pages="pages"
    :prev-label="t('catalog.pagination.prev')"
    :next-label="t('catalog.pagination.next')"
    :of-label="t('catalog.pagination.of', { page, pages })"
    @update:page="onPage"
  />
</template>
