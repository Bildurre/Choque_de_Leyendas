<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, FilterBar, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BaseSelect, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { HeroAbility } from '@juego/shared'
import HeroAbilityFormModal from '@/components/hero-abilities/HeroAbilityFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import SortSelect from '@/components/SortSelect.vue'
import CostDice from '@/components/game/CostDice.vue'

// Habilidades activas: sin single ni publicación (tabs all/trashed); la API
// resuelve por id y la edición rellena desde el ítem del listado. El listado
// filtra por tipo de ataque con un select en la barra de búsqueda.
const {
  t,
  items,
  loading,
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

/** Tipado completo en orden canónico: rango · tipo · subtipo · área. */
function typing(a: HeroAbility): string {
  const parts: string[] = []
  if (a.attack_range) parts.push(tr(a.attack_range.name))
  if (a.attack_type) parts.push(t(`heroAbilities.attackTypes.${a.attack_type}`))
  if (a.attack_subtype) parts.push(tr(a.attack_subtype.name))
  if (a.area) parts.push(t('heroAbilities.fields.area'))
  return parts.join(' · ')
}

onMounted(init)
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

    <!-- Filtros por encima de las tabs (estilo kontuan) -->
    <FilterBar v-model="search" :placeholder="t('common.search')">
      <BaseSelect v-model="filters.attack_type" :options="attackTypeOptions" />
      <SortSelect v-model="sort" />
    </FilterBar>
    <BaseTabs v-model="status" :tabs="tabs" />

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
        <template #badges>
          <span v-if="item.deleted_at" class="chip is-failed">{{
            t('heroAbilities.state.trashed')
          }}</span>
        </template>

        <template #meta>
          <CostDice v-if="item.cost" :cost="item.cost" />
          <span v-if="typing(item)">{{ typing(item) }}</span>
        </template>
      </EntityCard>
    </BaseGrid>

    <HeroAbilityFormModal v-model="formOpen" :mode="formMode" :target="formItem" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('heroAbilities.panelTitle')"
      :empty="t('heroAbilities.panelEmpty')"
      :has-single="false"
      :has-publish="false"
      @edit="selected && edit(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <template #meta>
        <!-- Tipado completo en orden canónico: rango · tipo · subtipo · área -->
        <p v-if="selected" class="manager-detail__meta">
          <CostDice v-if="selected.cost" :cost="selected.cost" />
          <span v-if="typing(selected)">{{ typing(selected) }}</span>
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
