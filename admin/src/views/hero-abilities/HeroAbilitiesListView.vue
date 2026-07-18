<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseSelect, BaseTabs } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useEntityList } from '@/composables/useEntityList'
import type { HeroAbility, TaxonomyOption } from '@juego/shared'
import HeroAbilityFormModal from '@/components/hero-abilities/HeroAbilityFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'
import CostDice from '@/components/game/CostDice.vue'
import AttackLine from '@/components/game/AttackLine.vue'

// Habilidades activas: sin single ni publicación (tabs all/trashed); la API
// resuelve por id y la edición rellena desde el ítem del listado. El listado
// filtra por tipo/rango/subtipo de ataque, área y coste total con selects en
// el panel derecho (mecanismo `filters` de useEntityList).
const {
  t,
  items,
  loading,
  page,
  pages,
  status,
  search,
  sort,
  filters,
  tabs,
  tr,
  init,
  formOpen,
  formMode,
  formItem,
  openCreate,
  edit,
  onSaved,
  del,
  restore,
  forceDelete,
  selectedId,
  selected,
  select,
} = useEntityList<HeroAbility>({
  resource: '/admin/hero-abilities',
  ns: 'heroAbilities',
  nameOf: (item) => item.name,
  resolveBy: 'id',
  tabKeys: ['all', 'trashed'],
})

const attackTypeOptions = computed(() => [
  { value: '', label: t('heroAbilities.filters.allAttackTypes') },
  { value: 'physical', label: t('heroAbilities.attackTypes.physical') },
  { value: 'magical', label: t('heroAbilities.attackTypes.magical') },
])

// Taxonomías de los selects de rango/subtipo (mismos options del modal).
const attackRanges = ref<TaxonomyOption[]>([])
const attackSubtypes = ref<TaxonomyOption[]>([])

async function loadFilterOptions() {
  try {
    const [ranges, subtypes] = await Promise.all([
      api.get('/admin/attack-ranges/options'),
      api.get('/admin/attack-subtypes/options'),
    ])
    attackRanges.value = ranges.data.data
    attackSubtypes.value = subtypes.data.data
  } catch {
    attackRanges.value = []
    attackSubtypes.value = []
  }
}

const attackRangeOptions = computed(() => [
  { value: '', label: t('heroAbilities.filters.allAttackRanges') },
  ...attackRanges.value.map((o) => ({ value: String(o.id), label: tr(o.name) })),
])

const attackSubtypeOptions = computed(() => [
  { value: '', label: t('heroAbilities.filters.allAttackSubtypes') },
  ...attackSubtypes.value.map((o) => ({ value: String(o.id), label: tr(o.name) })),
])

// area viaja como '1'/'0' ('' = no filtra), contrato del endpoint.
const areaOptions = computed(() => [
  { value: '', label: t('heroAbilities.filters.allAreas') },
  { value: '1', label: t('heroAbilities.filters.areaYes') },
  { value: '0', label: t('heroAbilities.filters.areaNo') },
])

// cost_total 1..5: el coste de una habilidad es obligatorio, 0 no existe.
const costOptions = computed(() => [
  { value: '', label: t('heroAbilities.filters.allCosts') },
  ...[1, 2, 3, 4, 5].map((n) => ({
    value: String(n),
    label: t('heroAbilities.filters.dice', { n }, n),
  })),
])

/**
 * Extracto del efecto para el meta de la card: el HTML del WYSIWYG pasa a
 * texto plano (DOMParser: no ejecuta ni carga nada) y se trunca a ~90
 * caracteres cortando en límite de palabra (si el corte cae razonablemente
 * cerca) con puntos suspensivos.
 */
function excerpt(item: HeroAbility, max = 90): string {
  const html = tr(item.description)
  if (html === '—') return ''
  const text = (new DOMParser().parseFromString(html, 'text/html').body.textContent || '')
    .replace(/\s+/g, ' ')
    .trim()
  if (text.length <= max) return text
  const cut = text.slice(0, max + 1)
  const lastSpace = cut.lastIndexOf(' ')
  return `${cut.slice(0, lastSpace > max * 0.6 ? lastSpace : max).trimEnd()}…`
}

onMounted(() => {
  init()
  loadFilterOptions()
})
</script>

<!-- eslint-disable vue/no-v-html -- HTML del WYSIWYG propio (sanitización en servidor) -->
<template>
  <div class="hero-abilities">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('heroAbilities.newButton') }}
      </BaseButton>
    </div>

    <!-- Barra del índice: búsqueda + orden (los filtros, en el panel derecho) -->
    <ListToolbar v-model="search" v-model:sort="sort" />
    <BaseTabs v-model="status" :tabs="tabs" />
    <BasePagination
      v-model:page="page"
      :pages="pages"
      class="list-view__pagination"
      :prev-label="t('common.pagination.prev')"
      :next-label="t('common.pagination.next')"
      :of-label="t('common.pagination.of', { page, pages })"
    />

    <EmptyState v-if="!loading && !items.length" :title="t('common.empty')" />

    <BaseGrid v-else preset="cards" gap="md">
      <EntityCard
        v-for="item in items"
        :key="item.id"
        :title="tr(item.name)"
        :muted="!!item.deleted_at"
        :active="selectedId === item.id"
        clickable
        :editable="!item.deleted_at"
        :edit-label="t('common.actions.edit')"
        @view="select(item)"
        @edit="edit(item)"
      >
        <!-- Sin badge de estado (los tabs ya separan): el tipado completo en
             badges, SIEMPRE en orden rango-tipo-subtipo (+ área); el tipo,
             coloreado (físico rojo / mágico azul) -->
        <template #badges>
          <span v-if="item.attack_range" class="chip">{{ tr(item.attack_range.name) }}</span>
          <span v-if="item.attack_type" class="chip" :class="`is-${item.attack_type}`">{{
            t(`heroAbilities.attackTypes.${item.attack_type}`)
          }}</span>
          <span v-if="item.attack_subtype" class="chip">{{ tr(item.attack_subtype.name) }}</span>
          <span v-if="item.area" class="chip">{{ t('heroAbilities.fields.area') }}</span>
        </template>

        <!-- Meta: coste (dados) + extracto del efecto en texto plano -->
        <template #meta>
          <CostDice v-if="item.cost" :cost="item.cost" />
          <span v-if="excerpt(item)" class="hero-abilities__excerpt">{{ excerpt(item) }}</span>
        </template>
      </EntityCard>
    </BaseGrid>

    <BasePagination
      v-model:page="page"
      :pages="pages"
      class="list-view__pagination list-view__pagination--bottom"
      :prev-label="t('common.pagination.prev')"
      :next-label="t('common.pagination.next')"
      :of-label="t('common.pagination.of', { page, pages })"
    />

    <HeroAbilityFormModal v-model="formOpen" :mode="formMode" :target="formItem" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('heroAbilities.panelTitle')"
      :empty="t('heroAbilities.panelEmpty')"
      :has-single="false"
      :has-publish="false"
      @deselect="selectedId = null"
      @edit="selected && edit(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <!-- Filtros del listado: aplican en vivo (sin guardar) -->
      <template #filters>
        <!-- Orden canónico del tipado: rango · tipo · subtipo · área -->
        <BaseSelect
          v-model="filters.attack_range_id"
          :label="t('heroAbilities.fields.attackRange')"
          :options="attackRangeOptions"
        />
        <BaseSelect
          v-model="filters.attack_type"
          :label="t('heroAbilities.fields.attackType')"
          :options="attackTypeOptions"
        />
        <BaseSelect
          v-model="filters.attack_subtype_id"
          :label="t('heroAbilities.fields.attackSubtype')"
          :options="attackSubtypeOptions"
        />
        <BaseSelect
          v-model="filters.area"
          :label="t('heroAbilities.fields.area')"
          :options="areaOptions"
        />
        <BaseSelect
          v-model="filters.cost_total"
          :label="t('heroAbilities.fields.cost')"
          :options="costOptions"
        />
      </template>

      <template #meta>
        <!-- Tipado completo en orden canónico rango-tipo-subtipo (+ área),
             con el tipo coloreado (AttackLine, sin chips en el panel) -->
        <p v-if="selected" class="manager-detail__meta hero-abilities__panel-meta">
          <CostDice v-if="selected.cost" :cost="selected.cost" />
          <AttackLine
            v-if="
              selected.attack_range ||
              selected.attack_type ||
              selected.attack_subtype ||
              selected.area
            "
            :range="selected.attack_range"
            :type="selected.attack_type"
            :subtype="selected.attack_subtype"
            :area="selected.area"
          />
        </p>
        <!-- HTML del WYSIWYG propio (saneado en origen) -->
        <div
          v-if="selected && tr(selected.description) !== '—'"
          class="hero-abilities__description"
          v-html="tr(selected.description)"
        ></div>
      </template>
    </EntityPanel>
  </div>
</template>
