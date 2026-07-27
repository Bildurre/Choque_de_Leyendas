<script setup lang="ts">
import { onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { AttackSubtype } from '@juego/shared'
import AttackSubtypeFormModal from '@/components/attack-subtypes/AttackSubtypeFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'
import PanelSection from '@/components/PanelSection.vue'

// Taxonomía simple: tabs solo all/trashed, sin single ni publicación;
// la API resuelve por id.
const {
  t,
  items,
  loading,
  page,
  pages,
  status,
  search,
  sort,
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
} = useEntityList<AttackSubtype>({
  resource: '/admin/attack-subtypes',
  ns: 'attackSubtypes',
  nameOf: (item) => item.name,
  resolveBy: 'id',
  tabKeys: ['all', 'trashed'],
})

onMounted(init)
</script>

<template>
  <div class="attack-subtypes">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('attackSubtypes.newButton') }}
      </BaseButton>
    </div>

    <!-- Barra del índice: búsqueda + toggles de ordenación -->
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
        <template #badges>
          <span v-if="item.deleted_at" class="chip is-failed">{{
            t('attackSubtypes.state.trashed')
          }}</span>
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

    <AttackSubtypeFormModal
      v-model="formOpen"
      :mode="formMode"
      :target="formItem"
      @saved="onSaved"
    />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('attackSubtypes.panelTitle')"
      :empty="t('attackSubtypes.panelEmpty')"
      :has-single="false"
      :has-publish="false"
      @edit="selected && edit(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <template #meta>
        <!-- Cantidades como ENLACES a cada index filtrado por este subtipo -->
        <PanelSection v-if="selected" :title="t('common.sections.collection')">
          <ul class="panel-counts">
            <li>
              <RouterLink
                class="hero-link"
                :to="{ name: 'cards', query: { attack_subtype_id: String(selected.id) } }"
                ><strong>{{ t('attackSubtypes.counts.cards') }}</strong></RouterLink
              ><span>{{ selected.cards_count ?? 0 }}</span>
            </li>
            <li>
              <RouterLink
                class="hero-link"
                :to="{ name: 'hero-abilities', query: { attack_subtype_id: String(selected.id) } }"
                ><strong>{{ t('attackSubtypes.counts.abilities') }}</strong></RouterLink
              ><span>{{ selected.hero_abilities_count ?? 0 }}</span>
            </li>
          </ul>
        </PanelSection>
      </template>
    </EntityPanel>
  </div>
</template>
