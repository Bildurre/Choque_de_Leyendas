<script setup lang="ts">
import { onMounted } from 'vue'
import { ArrowRight, Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, FilterBar, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { Hero } from '@juego/shared'
import HeroFormModal from '@/components/heroes/HeroFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import SortSelect from '@/components/SortSelect.vue'

// Héroes: entidad completa con slug, single, publicación y previews PNG.
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
  selectedId,
  selected,
  select,
} = useEntityList<Hero>({
  resource: '/admin/heroes',
  ns: 'heroes',
  singleRoute: 'hero-single',
  nameOf: (item) => item.name,
  previewKey: 'hero',
})

onMounted(init)
</script>

<template>
  <div class="heroes">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('heroes.newButton') }}
      </BaseButton>
    </div>

    <!-- Filtros por encima de las tabs (estilo kontuan) -->
    <FilterBar v-model="search" :placeholder="t('common.search')">
      <SortSelect v-model="sort" />
    </FilterBar>
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
        @view="select(item)"
      >
        <template #media>
          <div class="heroes__art">
            <img v-if="item.image" :src="item.image" alt="" />
            <span v-else class="heroes__mono">{{ tr(item.name).charAt(0) }}</span>
          </div>
        </template>

        <!-- La tarjeta solo lleva "entrar" al single; el resto, en el panel -->
        <template #actions>
          <button v-if="!item.deleted_at" type="button" class="card-enter" @click="goSingle(item)">
            {{ t('common.actions.enter') }} <ArrowRight :size="14" />
          </button>
        </template>

        <template #badges>
          <span v-if="item.deleted_at" class="chip is-failed">{{ t('heroes.state.trashed') }}</span>
          <span v-else-if="item.is_published" class="chip is-ok">{{
            t('heroes.state.published')
          }}</span>
          <span v-else class="chip">{{ t('heroes.state.draft') }}</span>
        </template>

        <template #meta>
          <span>{{ item.faction ? tr(item.faction.name) : t('heroes.fields.noFaction') }}</span>
          <span v-if="item.hero_class">· {{ tr(item.hero_class.name) }}</span>
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

    <HeroFormModal v-model="formOpen" :mode="formMode" :target-slug="formSlug" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('heroes.panelTitle')"
      :empty="t('heroes.panelEmpty')"
      has-preview
      @open="selected && goSingle(selected)"
      @edit="selected && edit(selected)"
      @toggle-publish="selected && togglePublish(selected)"
      @regenerate="selected && regeneratePreview(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <template #meta>
        <p v-if="selected" class="manager-detail__meta">
          <span>{{ selected.hero_race ? tr(selected.hero_race.name) : '—' }}</span>
          <span>· {{ selected.hero_class ? tr(selected.hero_class.name) : '—' }}</span>
        </p>
        <ul v-if="selected" class="heroes__stats">
          <li>
            <strong>{{ t('heroes.attributes.agility') }}</strong
            ><span>{{ selected.agility }}</span>
          </li>
          <li>
            <strong>{{ t('heroes.attributes.mental') }}</strong
            ><span>{{ selected.mental }}</span>
          </li>
          <li>
            <strong>{{ t('heroes.attributes.will') }}</strong
            ><span>{{ selected.will }}</span>
          </li>
          <li>
            <strong>{{ t('heroes.attributes.strength') }}</strong
            ><span>{{ selected.strength }}</span>
          </li>
          <li>
            <strong>{{ t('heroes.attributes.armor') }}</strong
            ><span>{{ selected.armor }}</span>
          </li>
          <li>
            <strong>{{ t('heroes.attributes.health') }}</strong
            ><span>{{ selected.health }}</span>
          </li>
        </ul>
      </template>
    </EntityPanel>
  </div>
</template>
