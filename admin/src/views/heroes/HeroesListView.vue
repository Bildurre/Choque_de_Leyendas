<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ArrowRight, FunnelX, Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs, MultiSelect } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import { api } from '@/lib/api'
import type { Hero, HeroAbilityRef, HeroClassOption, Translations } from '@juego/shared'
import HeroFormModal from '@/components/heroes/HeroFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'
import PanelSection from '@/components/PanelSection.vue'
import CostDice from '@/components/game/CostDice.vue'

// Héroes: entidad completa con slug, single, publicación y previews PNG.
// El listado filtra por facción, superclase, clase y raza con multiselects
// en el panel derecho (slot `filters` del EntityPanel), como en Cartas:
// cada filtro admite varios valores (unión) y viaja como `clave[]=`.
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
  // Filtros que aceptan enlaces entrantes por query (p. ej. desde el single:
  // ?hero_race_id=3). Mismos nombres que los selects del panel.
  queryFilters: ['faction_id', 'hero_superclass_id', 'hero_class_id', 'hero_race_id'],
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

// Sin opción "Todas" en la lista: sin nada marcado, el placeholder del
// MultiSelect ya dice "Todas las …".
const factionOptions = computed(() =>
  factions.value.map((f) => ({ value: String(f.id), label: tr(f.name) })),
)
const superclassOptions = computed(() =>
  superclasses.value.map((s) => ({ value: String(s.id), label: tr(s.name) })),
)
// Acotada por las superclases marcadas (si hay alguna): las clases de
// CUALQUIERA de ellas.
const classOptions = computed(() => {
  const chosen = filters.hero_superclass_id ?? []
  const bySuperclass = chosen.length
    ? classes.value.filter((c) => chosen.includes(String(c.hero_superclass_id)))
    : classes.value
  return bySuperclass.map((c) => ({ value: String(c.id), label: tr(c.name) }))
})
const raceOptions = computed(() =>
  races.value.map((r) => ({ value: String(r.id), label: tr(r.name) })),
)

// Las clases marcadas que dejen de pertenecer a alguna superclase marcada
// se limpian (las demás se quedan).
watch(
  () => filters.hero_superclass_id,
  () => {
    const chosen = filters.hero_class_id ?? []
    if (!chosen.length) return
    const valid = chosen.filter((id) => classOptions.value.some((o) => o.value === id))
    if (valid.length !== chosen.length) filters.hero_class_id = valid
  },
)

/** El héroe seleccionado tiene pasiva propia (nombre o descripción). */
function heroPassive(hero: Hero): boolean {
  return tr(hero.passive_name) !== '—' || tr(hero.passive_description) !== '—'
}

/** Slug localizado de la facción embebida (enlace a su single). */
function factionSlug(hero: Hero): string {
  const slug = hero.faction?.slug
  return slug?.[locales.current] || Object.values(slug ?? {})[0] || ''
}

/**
 * Enlace a la DEFINICIÓN de un término de taxonomía: el index de esa
 * taxonomía con el elemento seleccionado (selected lo marca y search — el
 * nombre NEUTRO, que es el que muestra ese index — lo deja en la primera
 * página), mismo patrón que las habilidades.
 */
function definitionLink(routeName: string, term: { id: number; name: Translations }) {
  return { name: routeName, query: { selected: String(term.id), search: tr(term.name) } }
}

/**
 * Enlace al index de habilidades con esta habilidad seleccionada: el search
 * acota la lista (el ítem cae en la primera página) y selected lo marca.
 */
function abilityLink(ability: HeroAbilityRef) {
  return {
    name: 'hero-abilities',
    query: { selected: String(ability.id), search: tr(ability.name) },
  }
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
             La de facción, chip TEÑIDO: su color de fondo y el texto en
             blanco/negro automático (is-tinted del motor) — el texto teñido
             sobre el fondo del chip no se leía con colores claros/oscuros. -->
        <template #badges>
          <span
            class="chip"
            :class="{ 'is-tinted': !!item.faction?.color }"
            :style="item.faction?.color ? { '--chip-tint': item.faction.color } : undefined"
            >{{ item.faction ? tr(item.faction.name) : t('heroes.fields.noFaction') }}</span
          >
          <!-- Raza, clase y superclase con el género del héroe (·_display) -->
          <span v-if="item.hero_race" class="chip">{{
            tr(item.race_display ?? item.hero_race.name)
          }}</span>
          <span v-if="item.hero_class" class="chip">{{
            tr(item.class_display ?? item.hero_class.name)
          }}</span>
          <span v-if="item.hero_class?.hero_superclass" class="chip">{{
            tr(item.superclass_display ?? item.hero_class.hero_superclass.name)
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
      <!-- Filtros del listado: aplican en vivo (sin guardar), multivalor -->
      <template #filters>
        <MultiSelect
          v-model="filters.faction_id"
          :label="t('heroes.fields.faction')"
          :placeholder="t('heroes.filters.allFactions')"
          :options="factionOptions"
        />
        <MultiSelect
          v-model="filters.hero_superclass_id"
          :label="t('heroes.fields.superclass')"
          :placeholder="t('heroes.filters.allSuperclasses')"
          :options="superclassOptions"
        />
        <MultiSelect
          v-model="filters.hero_class_id"
          :label="t('heroes.fields.class')"
          :placeholder="t('heroes.filters.allClasses')"
          :options="classOptions"
        />
        <MultiSelect
          v-model="filters.hero_race_id"
          :label="t('heroes.fields.race')"
          :placeholder="t('heroes.filters.allRaces')"
          :options="raceOptions"
        />
        <BaseButton variant="text" type="button" @click="clearFilters">
          <template #icon><FunnelX :size="14" /></template>
          {{ t('common.filters.clear') }}
        </BaseButton>
      </template>

      <template #meta>
        <!-- Identidad en DOS filas: la facción (enlace a su single, sin caja
             de color) y debajo raza · clase · superclase (enlazan a su
             definición: el index de su taxonomía con el término seleccionado) -->
        <div v-if="selected" class="heroes__identity">
          <p class="manager-detail__meta">
            <RouterLink
              v-if="selected.faction && factionSlug(selected)"
              class="hero-link"
              :title="t('heroes.fields.faction')"
              :to="{ name: 'faction-single', params: { slug: factionSlug(selected) } }"
            >
              {{ tr(selected.faction.name) }}
            </RouterLink>
            <span v-else>{{
              selected.faction ? tr(selected.faction.name) : t('heroes.fields.noFaction')
            }}</span>
          </p>
          <!-- Raza, clase y superclase con el género del héroe (·_display);
               cada término enlaza a SU DEFINICIÓN (el index de su taxonomía
               con el elemento seleccionado) -->
          <p class="manager-detail__meta">
            <template v-if="selected.hero_race">
              <RouterLink
                class="hero-link"
                :title="t('heroes.fields.race')"
                :to="definitionLink('hero-races', selected.hero_race)"
              >
                {{ tr(selected.race_display ?? selected.hero_race.name) }}
              </RouterLink>
            </template>
            <template v-if="selected.hero_class">
              <span>·</span>
              <RouterLink
                class="hero-link"
                :title="t('heroes.fields.class')"
                :to="definitionLink('hero-classes', selected.hero_class)"
              >
                {{ tr(selected.class_display ?? selected.hero_class.name) }}
              </RouterLink>
            </template>
            <template v-if="selected.hero_class?.hero_superclass">
              <span>·</span>
              <RouterLink
                class="hero-link"
                :title="t('heroes.fields.superclass')"
                :to="definitionLink('hero-superclasses', selected.hero_class.hero_superclass)"
              >
                {{ tr(selected.superclass_display ?? selected.hero_class.hero_superclass.name) }}
              </RouterLink>
            </template>
          </p>
        </div>

        <!-- Secciones con el lenguaje común del panel (PanelSection) -->
        <PanelSection v-if="selected" :title="t('heroes.sections.attributes')">
          <ul class="heroes__stats">
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
        </PanelSection>

        <!-- Habilidades LIGERAS: solo nombre (enlace al index de habilidades
             con esa habilidad seleccionada) + coste; los textos, en el single -->
        <PanelSection
          v-if="selected && selected.abilities?.length"
          :title="t('heroes.sections.abilities')"
        >
          <ul class="heroes__panel-abilities">
            <li v-for="ability in selected.abilities" :key="ability.id">
              <RouterLink class="hero-link" :to="abilityLink(ability)">{{
                tr(ability.name)
              }}</RouterLink>
              <CostDice v-if="ability.cost" :cost="ability.cost" />
            </li>
          </ul>
        </PanelSection>

        <!-- Pasiva DEL HÉROE: nombre + texto (el nombre solo no decía nada) -->
        <PanelSection
          v-if="selected && heroPassive(selected)"
          :title="t('heroes.sections.passive')"
        >
          <p class="heroes__panel-passive">
            <strong v-if="tr(selected.passive_name) !== '—'"
              >{{ tr(selected.passive_name) }}:</strong
            >
            <!-- eslint-disable vue/no-v-html -- wysiwyg propio, saneado en servidor (DC-09) -->
            <span
              v-if="tr(selected.passive_description) !== '—'"
              class="heroes__panel-passive-text"
              v-html="tr(selected.passive_description)"
            />
            <!-- eslint-enable vue/no-v-html -->
          </p>
        </PanelSection>
      </template>
    </EntityPanel>
  </div>
</template>
