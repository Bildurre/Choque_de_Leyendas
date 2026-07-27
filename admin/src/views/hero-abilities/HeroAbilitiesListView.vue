<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { FunnelX, Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs, MultiSelect } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useEntityList } from '@/composables/useEntityList'
import type { HeroAbility, SingleLinkRef, TaxonomyOption } from '@juego/shared'
import HeroAbilityFormModal from '@/components/hero-abilities/HeroAbilityFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'
import PanelSection from '@/components/PanelSection.vue'
import CostDice from '@/components/game/CostDice.vue'
import AttackLine from '@/components/game/AttackLine.vue'

// Habilidades activas: sin single ni publicación (tabs all/trashed); la API
// resuelve por id y la edición rellena desde el ítem del listado. El listado
// filtra por tipo/rango/subtipo de ataque, área y coste total con
// multiselects en el panel derecho (mecanismo `filters` de useEntityList):
// varios valores por filtro (unión), viajan como `clave[]=`.
const {
  t,
  locales,
  items,
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
  // Enlaces entrantes desde los paneles de rangos/subtipos de ataque.
  queryFilters: ['attack_range_id', 'attack_subtype_id'],
})

// Sin opción "Todos" en las listas: sin nada marcado, el placeholder del
// MultiSelect ya dice "Todos los …".
const attackTypeOptions = computed(() => [
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

const attackRangeOptions = computed(() =>
  attackRanges.value.map((o) => ({ value: String(o.id), label: tr(o.name) })),
)

const attackSubtypeOptions = computed(() =>
  attackSubtypes.value.map((o) => ({ value: String(o.id), label: tr(o.name) })),
)

// area viaja como '1'/'0' (sin nada marcado = no filtra), contrato del endpoint.
const areaOptions = computed(() => [
  { value: '1', label: t('heroAbilities.filters.areaYes') },
  { value: '0', label: t('heroAbilities.filters.areaNo') },
])

// cost_total 1..5: el coste de una habilidad es obligatorio, 0 no existe.
const costOptions = computed(() =>
  [1, 2, 3, 4, 5].map((n) => ({
    value: String(n),
    label: t('heroAbilities.filters.dice', { n }, n),
  })),
)

/** Slug localizado de un héroe/carta embebido (enlace a su single). */
function slugOf(item: SingleLinkRef): string {
  return item.slug?.[locales.current] || Object.values(item.slug ?? {})[0] || ''
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
        <!-- Sin badge de estado (los tabs ya separan): el coste (dados)
             DELANTE, abriendo la zona (la EntityCard no tiene slot de
             título), y el tipado completo en badges, SIEMPRE en orden
             rango-tipo-subtipo (+ área); el tipo, coloreado (físico rojo /
             mágico azul) -->
        <template #badges>
          <CostDice v-if="item.cost" :cost="item.cost" />
          <span v-if="item.attack_range" class="chip">{{ tr(item.attack_range.name) }}</span>
          <span v-if="item.attack_type" class="chip" :class="`is-${item.attack_type}`">{{
            t(`heroAbilities.attackTypes.${item.attack_type}`)
          }}</span>
          <span v-if="item.attack_subtype" class="chip">{{ tr(item.attack_subtype.name) }}</span>
          <span v-if="item.area" class="chip">{{ t('heroAbilities.fields.area') }}</span>
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
      <!-- Filtros del listado: aplican en vivo (sin guardar), multivalor -->
      <template #filters>
        <!-- Orden canónico del tipado: rango · tipo · subtipo · área -->
        <MultiSelect
          v-model="filters.attack_range_id"
          :label="t('heroAbilities.fields.attackRange')"
          :placeholder="t('heroAbilities.filters.allAttackRanges')"
          :options="attackRangeOptions"
        />
        <MultiSelect
          v-model="filters.attack_type"
          :label="t('heroAbilities.fields.attackType')"
          :placeholder="t('heroAbilities.filters.allAttackTypes')"
          :options="attackTypeOptions"
        />
        <MultiSelect
          v-model="filters.attack_subtype_id"
          :label="t('heroAbilities.fields.attackSubtype')"
          :placeholder="t('heroAbilities.filters.allAttackSubtypes')"
          :options="attackSubtypeOptions"
        />
        <MultiSelect
          v-model="filters.area"
          :label="t('heroAbilities.fields.area')"
          :placeholder="t('heroAbilities.filters.allAreas')"
          :options="areaOptions"
        />
        <MultiSelect
          v-model="filters.cost_total"
          :label="t('heroAbilities.fields.cost')"
          :placeholder="t('heroAbilities.filters.allCosts')"
          :options="costOptions"
        />
        <BaseButton variant="text" type="button" @click="clearFilters">
          <template #icon><FunnelX :size="14" /></template>
          {{ t('common.filters.clear') }}
        </BaseButton>
      </template>

      <template #meta>
        <!-- Tipado completo en orden canónico rango-tipo-subtipo (+ área),
             con el tipo coloreado (AttackLine, sin chips en el panel); rango
             y subtipo enlazan a su definición -->
        <p v-if="selected" class="manager-detail__meta hero-abilities__panel-meta">
          <CostDice v-if="selected.cost" :cost="selected.cost" />
          <AttackLine
            v-if="
              selected.attack_range ||
              selected.attack_type ||
              selected.attack_subtype ||
              selected.area
            "
            linked
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
        <!-- Los héroes que tienen la habilidad, cada uno enlazado a su
             single por el slug del locale activo -->
        <PanelSection
          v-if="selected"
          :title="`${t('heroAbilities.counts.heroes')} (${selected.heroes_count ?? selected.heroes?.length ?? 0})`"
        >
          <ul class="panel-counts">
            <li v-for="hero in selected.heroes ?? []" :key="hero.id">
              <RouterLink
                class="hero-link"
                :to="{ name: 'hero-single', params: { slug: slugOf(hero) } }"
                >{{ tr(hero.name) }}</RouterLink
              >
            </li>
          </ul>
        </PanelSection>
        <!-- Las cartas que otorgan la habilidad, enlazadas a su single -->
        <PanelSection
          v-if="selected"
          :title="`${t('heroAbilities.counts.cards')} (${selected.cards_count ?? selected.cards?.length ?? 0})`"
        >
          <ul class="panel-counts">
            <li v-for="card in selected.cards ?? []" :key="card.id">
              <RouterLink
                class="hero-link"
                :to="{ name: 'card-single', params: { slug: slugOf(card) } }"
                >{{ tr(card.name) }}</RouterLink
              >
            </li>
          </ul>
        </PanelSection>
      </template>
    </EntityPanel>
  </div>
</template>
