<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ArrowRight, Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseSelect, BaseTabs } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import { api } from '@/lib/api'
import type { Hero, HeroClassOption, Translations } from '@juego/shared'
import HeroFormModal from '@/components/heroes/HeroFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'
import CostDice from '@/components/game/CostDice.vue'

// Héroes: entidad completa con slug, single, publicación y previews PNG.
// El listado filtra por facción, superclase, clase y raza con selects en
// el panel derecho (slot `filters` del EntityPanel), como en Cartas.
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

// Opciones de los selects de filtro del panel (endpoints options, nombres
// traducibles). La clase incluye su superclase, para acotar en cascada.
interface FilterOption {
  id: number
  name: Translations
}
const factions = ref<FilterOption[]>([])
const superclasses = ref<FilterOption[]>([])
const classes = ref<HeroClassOption[]>([])
const races = ref<FilterOption[]>([])

const factionOptions = computed(() => [
  { value: '', label: t('heroes.filters.allFactions') },
  ...factions.value.map((f) => ({ value: String(f.id), label: tr(f.name) })),
])
const superclassOptions = computed(() => [
  { value: '', label: t('heroes.filters.allSuperclasses') },
  ...superclasses.value.map((s) => ({ value: String(s.id), label: tr(s.name) })),
])
// Acotada por la superclase elegida (si hay una): solo sus clases.
const classOptions = computed(() => {
  const bySuperclass = filters.hero_superclass_id
    ? classes.value.filter((c) => String(c.hero_superclass_id) === filters.hero_superclass_id)
    : classes.value
  return [
    { value: '', label: t('heroes.filters.allClasses') },
    ...bySuperclass.map((c) => ({ value: String(c.id), label: tr(c.name) })),
  ]
})
const raceOptions = computed(() => [
  { value: '', label: t('heroes.filters.allRaces') },
  ...races.value.map((r) => ({ value: String(r.id), label: tr(r.name) })),
])

// Si la clase elegida deja de pertenecer a la superclase elegida, se limpia.
watch(
  () => filters.hero_superclass_id,
  () => {
    if (!filters.hero_class_id) return
    const stillValid = classOptions.value.some((o) => o.value === filters.hero_class_id)
    if (!stillValid) filters.hero_class_id = ''
  },
)

/** El héroe seleccionado tiene pasiva propia (nombre o descripción). */
function heroPassive(hero: Hero): boolean {
  return tr(hero.passive_name) !== '—' || tr(hero.passive_description) !== '—'
}

async function loadFilterOptions() {
  try {
    const [factionsRes, superclassesRes, classesRes, racesRes] = await Promise.all([
      api.get('/admin/factions/options'),
      api.get('/admin/hero-superclasses/options'),
      api.get('/admin/hero-classes/options'),
      api.get('/admin/hero-races/options'),
    ])
    factions.value = factionsRes.data.data
    superclasses.value = superclassesRes.data.data
    classes.value = classesRes.data.data
    races.value = racesRes.data.data
  } catch {
    // Sin opciones no hay filtro, pero el listado sigue funcionando.
  }
}

onMounted(async () => {
  await Promise.all([init(), loadFilterOptions()])
})
</script>

<!-- eslint-disable vue/no-v-html -- HTML del WYSIWYG propio (sanitización en servidor) -->
<template>
  <div class="heroes">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('heroes.newButton') }}
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
        :accent-color="item.faction?.color || undefined"
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

        <!-- Sin badge de estado (los tabs ya separan): facción, raza y clase.
             La de facción va teñida con su color identitario. -->
        <template #badges>
          <span
            class="chip"
            :style="item.faction?.color ? { color: item.faction.color } : undefined"
            >{{ item.faction ? tr(item.faction.name) : t('heroes.fields.noFaction') }}</span
          >
          <!-- Raza y clase con el género del héroe (·_display) -->
          <span v-if="item.hero_race" class="chip">{{
            tr(item.race_display ?? item.hero_race.name)
          }}</span>
          <span v-if="item.hero_class" class="chip">{{
            tr(item.class_display ?? item.hero_class.name)
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

    <HeroFormModal v-model="formOpen" :mode="formMode" :target-slug="formSlug" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('heroes.panelTitle')"
      :empty="t('heroes.panelEmpty')"
      has-preview
      @deselect="selectedId = null"
      @open="selected && goSingle(selected)"
      @edit="selected && edit(selected)"
      @toggle-publish="selected && togglePublish(selected)"
      @regenerate="selected && regeneratePreview(selected)"
      @del="selected && del(selected)"
      @restore="selected && restore(selected)"
      @force-delete="selected && forceDelete(selected)"
    >
      <!-- Filtros del listado: aplican en vivo (sin guardar) -->
      <template #filters>
        <BaseSelect
          v-model="filters.faction_id"
          :label="t('heroes.fields.faction')"
          :options="factionOptions"
        />
        <BaseSelect
          v-model="filters.hero_superclass_id"
          :label="t('heroes.fields.superclass')"
          :options="superclassOptions"
        />
        <BaseSelect
          v-model="filters.hero_class_id"
          :label="t('heroes.fields.class')"
          :options="classOptions"
        />
        <BaseSelect
          v-model="filters.hero_race_id"
          :label="t('heroes.fields.race')"
          :options="raceOptions"
        />
      </template>

      <template #meta>
        <p v-if="selected" class="manager-detail__meta">
          <!-- Raza y clase con el género del héroe (·_display) -->
          <span>{{
            selected.hero_race ? tr(selected.race_display ?? selected.hero_race.name) : '—'
          }}</span>
          <span
            >·
            {{
              selected.hero_class ? tr(selected.class_display ?? selected.hero_class.name) : '—'
            }}</span
          >
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

        <!-- Habilidades activas y pasiva DEL HÉROE (la de clase, en el single) -->
        <template v-if="selected && selected.abilities?.length">
          <h4 class="heroes__panel-title">{{ t('heroes.sections.abilities') }}</h4>
          <ul class="heroes__panel-abilities">
            <li v-for="ability in selected.abilities" :key="ability.id">
              <span>{{ tr(ability.name) }}</span>
              <CostDice v-if="ability.cost" :cost="ability.cost" />
            </li>
          </ul>
        </template>
        <template v-if="selected && heroPassive(selected)">
          <h4 class="heroes__panel-title">{{ t('heroes.sections.passive') }}</h4>
          <p v-if="tr(selected.passive_name) !== '—'" class="heroes__panel-passive-name">
            {{ tr(selected.passive_name) }}
          </p>
          <div
            v-if="tr(selected.passive_description) !== '—'"
            class="rich-content heroes__panel-passive"
            v-html="tr(selected.passive_description)"
          ></div>
        </template>
      </template>
    </EntityPanel>
  </div>
</template>
