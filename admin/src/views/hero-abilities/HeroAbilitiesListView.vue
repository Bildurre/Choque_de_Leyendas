<script setup lang="ts">
import { onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, FilterBar, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import type { HeroAbility } from '@juego/shared'
import HeroAbilityFormModal from '@/components/hero-abilities/HeroAbilityFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'

// Habilidades activas: sin single ni publicación (tabs all/trashed); la API
// resuelve por id y la edición rellena desde el ítem del listado.
const {
  t,
  items,
  loading,
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

/** Letras del coste con su color para pintarlas como dados (CSS plano). */
function dice(cost: string): { letter: string; color: string }[] {
  const colors: Record<string, string> = { R: 'red', G: 'green', B: 'blue' }
  return (cost || '')
    .split('')
    .filter((letter) => colors[letter])
    .map((letter) => ({ letter, color: colors[letter] }))
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
    <FilterBar v-model="search" :placeholder="t('common.search')" />
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
        @view="select(item)"
      >
        <template #media>
          <div class="hero-abilities__emblem">
            <span class="hero-abilities__mono">{{ tr(item.name).charAt(0) }}</span>
          </div>
        </template>

        <template #badges>
          <span v-if="item.deleted_at" class="chip is-failed">{{
            t('heroAbilities.state.trashed')
          }}</span>
          <span v-if="item.area" class="chip">{{ t('heroAbilities.fields.area') }}</span>
        </template>

        <template #meta>
          <span class="hero-abilities__cost">
            <span
              v-for="(d, i) in dice(item.cost)"
              :key="`${d.letter}-${i}`"
              class="hero-abilities__die"
              :class="`hero-abilities__die--${d.color}`"
              >{{ d.letter }}</span
            >
          </span>
          <span v-if="item.attack_type">{{
            t(`heroAbilities.attackTypes.${item.attack_type}`)
          }}</span>
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
        <p v-if="selected" class="manager-detail__meta">
          <span class="hero-abilities__cost">
            <span
              v-for="(d, i) in dice(selected.cost)"
              :key="`${d.letter}-${i}`"
              class="hero-abilities__die"
              :class="`hero-abilities__die--${d.color}`"
              >{{ d.letter }}</span
            >
          </span>
          <span v-if="selected.attack_type">
            {{ t(`heroAbilities.attackTypes.${selected.attack_type}`) }}
          </span>
          <span v-if="selected.attack_range">· {{ tr(selected.attack_range.name) }}</span>
          <span v-if="selected.attack_subtype">· {{ tr(selected.attack_subtype.name) }}</span>
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
