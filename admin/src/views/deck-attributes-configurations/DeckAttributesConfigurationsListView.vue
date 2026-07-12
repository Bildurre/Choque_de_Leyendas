<script setup lang="ts">
import { onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, FilterBar, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { DeckAttributesConfig } from '@juego/shared'
import DeckAttributesConfigurationFormModal from '@/components/deck-attributes-configurations/DeckAttributesConfigurationFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'

// Configuraciones de mazo: CRUD por id, sin publicación, sin papelera y sin
// single (todo se edita en el modal). El "nombre" es el modo de juego, así
// que no lleva SortSelect: la API ordena siempre por id desc.
const {
  t,
  items,
  loading,
  page,
  pages,
  status,
  search,
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
  selectedId,
  selected,
  select,
} = useEntityList<DeckAttributesConfig>({
  resource: '/admin/deck-attributes-configurations',
  ns: 'deckAttributesConfigs',
  resolveBy: 'id',
  tabKeys: ['all'],
  nameOf: (item) => item.game_mode?.name ?? {},
})

/** Nombre a mostrar: el modo de juego o "genérica" si no tiene. */
function configName(item: DeckAttributesConfig): string {
  return item.game_mode ? tr(item.game_mode.name) : t('deckAttributesConfigs.fields.noGameMode')
}

onMounted(init)
</script>

<template>
  <div class="deck-configs">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('deckAttributesConfigs.newButton') }}
      </BaseButton>
    </div>

    <FilterBar v-model="search" :placeholder="t('common.search')" />
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
        :title="configName(item)"
        :active="selectedId === item.id"
        clickable
        editable
        :edit-label="t('common.actions.edit')"
        @view="select(item)"
        @edit="edit(item)"
      >
        <template #meta>
          <span>
            {{
              t('deckAttributesConfigs.summary.cards', {
                min: item.min_cards,
                max: item.max_cards,
              })
            }}
            ·
            {{ t('deckAttributesConfigs.summary.copies', { max: item.max_copies_per_card }) }}
            ·
            {{ t('deckAttributesConfigs.summary.heroes', { count: item.required_heroes }) }}
          </span>
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

    <DeckAttributesConfigurationFormModal
      v-model="formOpen"
      :mode="formMode"
      :target="formItem"
      @saved="onSaved"
    />

    <EntityPanel
      :item="selected"
      :name="selected ? configName(selected) : ''"
      :kicker="t('deckAttributesConfigs.panelTitle')"
      :empty="t('deckAttributesConfigs.panelEmpty')"
      :has-single="false"
      :has-publish="false"
      @edit="selected && edit(selected)"
      @del="selected && del(selected)"
    >
      <template #meta>
        <dl v-if="selected" class="deck-configs__facts">
          <div>
            <dt>{{ t('deckAttributesConfigs.fields.minCards') }}</dt>
            <dd>{{ selected.min_cards }}</dd>
          </div>
          <div>
            <dt>{{ t('deckAttributesConfigs.fields.maxCards') }}</dt>
            <dd>{{ selected.max_cards }}</dd>
          </div>
          <div>
            <dt>{{ t('deckAttributesConfigs.fields.maxCopiesPerCard') }}</dt>
            <dd>{{ selected.max_copies_per_card }}</dd>
          </div>
          <div>
            <dt>{{ t('deckAttributesConfigs.fields.requiredHeroes') }}</dt>
            <dd>{{ selected.required_heroes }}</dd>
          </div>
        </dl>
      </template>
    </EntityPanel>
  </div>
</template>
