<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ArrowRight, FunnelX, Plus } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BasePagination, BaseTabs, MultiSelect } from '@edc-motor/ui'
import { useEntityList } from '@/composables/useEntityList'
import { api } from '@/lib/api'
import type { Card, CardTypeOption, EquipmentSubtypeOption, Translations } from '@juego/shared'
import CardFormModal from '@/components/cards/CardFormModal.vue'
import EntityPanel from '@/components/EntityPanel.vue'
import ListToolbar from '@/components/ListToolbar.vue'
import CostDice from '@/components/game/CostDice.vue'
import AttackLine from '@/components/game/AttackLine.vue'
import CardEffect from '@/components/cards/CardEffect.vue'

// Cartas: entidad completa con slug, single, publicación y previews PNG.
// El listado filtra con multiselects en el panel derecho (slot `filters`
// del EntityPanel): facción, tipo y subtipo siempre y, según los flags de
// los tipos marcados (mismas condiciones que el índice público de la app),
// equipo (is_equipment) y ataque (allows_subtypes). Cada filtro admite
// varios valores (unión) y viaja como `clave[]=`.
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
} = useEntityList<Card>({
  resource: '/admin/cards',
  ns: 'cards',
  singleRoute: 'card-single',
  nameOf: (item) => item.name,
  previewKey: 'card',
  // Enlaces entrantes por query (p. ej. desde el panel/single de facción o
  // el tipado del single de carta). Mismos nombres que los selects del panel.
  queryFilters: [
    'faction_id',
    'card_type_id',
    'card_subtype_id',
    'equipment_type_id',
    'equipment_subtype_id',
    'attack_range_id',
    'attack_type',
    'attack_subtype_id',
    'area',
  ],
})

// Opciones de los selects de filtro del panel (endpoints options, nombres
// traducibles). Tipos con sus flags (deciden qué filtros condicionales
// aplican) y subtipos de equipo con su tipo (para acotar en cascada).
interface FilterOption {
  id: number
  name: Translations
}
const factions = ref<FilterOption[]>([])
const cardTypes = ref<CardTypeOption[]>([])
const cardSubtypes = ref<FilterOption[]>([])
const equipmentTypes = ref<FilterOption[]>([])
const equipmentSubtypes = ref<EquipmentSubtypeOption[]>([])
const attackRanges = ref<FilterOption[]>([])
const attackSubtypes = ref<FilterOption[]>([])

// Sin opción "Todas" en la lista: sin nada marcado, el placeholder del
// MultiSelect ya dice "Todas las …".
function toOptions(options: FilterOption[]) {
  return options.map((option) => ({ value: String(option.id), label: tr(option.name) }))
}
const factionOptions = computed(() => toOptions(factions.value))
const cardTypeOptions = computed(() => toOptions(cardTypes.value))
const cardSubtypeOptions = computed(() => toOptions(cardSubtypes.value))
const equipmentTypeOptions = computed(() => toOptions(equipmentTypes.value))
// Subtipos de equipo acotados a los tipos de equipo marcados (todos si no hay).
const equipmentSubtypeOptions = computed(() => {
  const chosen = filters.equipment_type_id ?? []
  return toOptions(
    equipmentSubtypes.value.filter(
      (option) => !chosen.length || chosen.includes(String(option.equipment_type_id)),
    ),
  )
})
const attackRangeOptions = computed(() => toOptions(attackRanges.value))
const attackTypeOptions = computed(() => [
  { value: 'physical', label: t('cards.attackTypes.physical') },
  { value: 'magical', label: t('cards.attackTypes.magical') },
])
const attackSubtypeOptions = computed(() => toOptions(attackSubtypes.value))
const areaOptions = computed(() => [
  { value: '1', label: t('cards.filters.areaYes') },
  { value: '0', label: t('cards.filters.areaNo') },
])

// Filtros condicionales según los flags de los tipos marcados (basta con
// que UN tipo marcado los aplique), como en el índice público de la app.
const selectedTypes = computed(() =>
  cardTypes.value.filter((type) => (filters.card_type_id ?? []).includes(String(type.id))),
)
const showEquipment = computed(() => selectedTypes.value.some((type) => type.is_equipment))
const showAttack = computed(() => selectedTypes.value.some((type) => type.allows_subtypes))

// Al cambiar los tipos se limpian los condicionales que dejen de aplicar
// (también al cargar las opciones, para sanear queries entrantes).
watch([selectedTypes, cardTypes], () => {
  if (!cardTypes.value.length) return
  if (!showEquipment.value) {
    if (filters.equipment_type_id?.length) filters.equipment_type_id = []
    if (filters.equipment_subtype_id?.length) filters.equipment_subtype_id = []
  }
  if (!showAttack.value) {
    if (filters.attack_range_id?.length) filters.attack_range_id = []
    if (filters.attack_type?.length) filters.attack_type = []
    if (filters.attack_subtype_id?.length) filters.attack_subtype_id = []
    if (filters.area?.length) filters.area = []
  }
})

// Al cambiar los tipos de equipo se limpian los subtipos marcados que no
// pertenezcan a ninguno (los demás se quedan).
watch([() => filters.equipment_type_id, equipmentSubtypes], () => {
  const chosen = filters.equipment_subtype_id ?? []
  if (!chosen.length || !equipmentSubtypes.value.length) return
  const valid = chosen.filter((id) =>
    equipmentSubtypeOptions.value.some((option) => option.value === id),
  )
  if (valid.length !== chosen.length) filters.equipment_subtype_id = valid
})

/** La carta tiene algo que pintar en la sección de efecto del panel. */
function hasEffectContent(item: Card): boolean {
  return tr(item.effect) !== '—' || tr(item.restriction) !== '—' || !!item.hero_ability
}

/** La carta lleva línea de ataque (rango, tipo o subtipo). */
function hasAttack(item: Card): boolean {
  return !!(item.attack_range || item.attack_type || item.attack_subtype)
}

/** Slug localizado de la facción embebida (enlace a su single). */
function factionSlug(item: Card): string {
  const slug = item.faction?.slug
  return slug?.[locales.current] || Object.values(slug ?? {})[0] || ''
}

/**
 * Enlace a la DEFINICIÓN de un término de taxonomía del tipado: el index de
 * esa taxonomía con el elemento seleccionado (selected lo marca y search lo
 * deja en la primera página), mismo patrón que las habilidades.
 */
function definitionLink(routeName: string, term: { id: number; name: Translations }) {
  return { name: routeName, query: { selected: String(term.id), search: tr(term.name) } }
}

async function loadFilterOptions() {
  try {
    const [factionsRes, typesRes, subtypesRes, eqTypesRes, eqSubtypesRes, rangesRes, atkSubsRes] =
      await Promise.all([
        api.get('/admin/factions/options'),
        api.get('/admin/card-types/options'),
        api.get('/admin/card-subtypes/options'),
        api.get('/admin/equipment-types/options'),
        api.get('/admin/equipment-subtypes/options'),
        api.get('/admin/attack-ranges/options'),
        api.get('/admin/attack-subtypes/options'),
      ])
    factions.value = factionsRes.data.data
    cardTypes.value = typesRes.data.data
    cardSubtypes.value = subtypesRes.data.data
    equipmentTypes.value = eqTypesRes.data.data
    equipmentSubtypes.value = eqSubtypesRes.data.data
    attackRanges.value = rangesRes.data.data
    attackSubtypes.value = atkSubsRes.data.data
  } catch {
    // Sin opciones no hay filtro, pero el listado sigue funcionando.
  }
}

onMounted(async () => {
  await Promise.all([init(), loadFilterOptions()])
})
</script>

<template>
  <div class="cards-view">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('cards.newButton') }}
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
        :accent-color="item.faction?.color || undefined"
        clickable
        @view="select(item)"
      >
        <template #media>
          <div class="card-art">
            <img v-if="item.image" :src="item.image" alt="" />
            <span v-else class="card-art__cost">{{ tr(item.name).charAt(0) }}</span>
          </div>
        </template>

        <!-- La tarjeta solo lleva "entrar" al single; el resto, en el panel -->
        <template #actions>
          <button v-if="!item.deleted_at" type="button" class="card-enter" @click="goSingle(item)">
            {{ t('common.actions.enter') }} <ArrowRight :size="14" />
          </button>
        </template>

        <!-- Sin badge de estado (los tabs ya separan): SOLO chips — facción
             (chip TEÑIDO: fondo de su color y texto en blanco/negro
             automático, is-tinted del motor) y el tipado completo: tipo,
             subtipo, equipo, manos y única (ámbar). El coste y la línea de
             ataque viven en el panel derecho. -->
        <template #badges>
          <span
            class="chip"
            :class="{ 'is-tinted': !!item.faction?.color }"
            :style="item.faction?.color ? { '--chip-tint': item.faction.color } : undefined"
            >{{ item.faction ? tr(item.faction.name) : t('cards.fields.noFaction') }}</span
          >
          <span v-if="item.card_type" class="chip">{{ tr(item.card_type.name) }}</span>
          <span v-if="item.card_subtype" class="chip">{{ tr(item.card_subtype.name) }}</span>
          <span v-if="item.equipment_type" class="chip">{{ tr(item.equipment_type.name) }}</span>
          <span v-if="item.equipment_subtype" class="chip">{{
            tr(item.equipment_subtype.name)
          }}</span>
          <span v-if="item.hands" class="chip">{{
            t(item.hands > 1 ? 'cards.fields.twoHands' : 'cards.fields.oneHand')
          }}</span>
          <span v-if="item.is_unique" class="chip is-unique">{{ t('cards.state.unique') }}</span>
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

    <CardFormModal v-model="formOpen" :mode="formMode" :target-slug="formSlug" @saved="onSaved" />

    <EntityPanel
      :item="selected"
      :name="selected ? tr(selected.name) : ''"
      :kicker="t('cards.panelTitle')"
      :empty="t('cards.panelEmpty')"
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
      <!-- Filtros del listado: aplican en vivo (sin guardar), multivalor.
           Equipo y ataque solo si algún tipo marcado los aplica (flags). -->
      <template #filters>
        <MultiSelect
          v-model="filters.faction_id"
          :label="t('cards.fields.faction')"
          :placeholder="t('cards.filters.allFactions')"
          :options="factionOptions"
        />
        <MultiSelect
          v-model="filters.card_type_id"
          :label="t('cards.fields.type')"
          :placeholder="t('cards.filters.allTypes')"
          :options="cardTypeOptions"
        />
        <MultiSelect
          v-model="filters.card_subtype_id"
          :label="t('cards.fields.subtype')"
          :placeholder="t('cards.filters.allSubtypes')"
          :options="cardSubtypeOptions"
        />
        <template v-if="showEquipment">
          <MultiSelect
            v-model="filters.equipment_type_id"
            :label="t('cards.fields.equipmentType')"
            :placeholder="t('cards.filters.allEquipmentTypes')"
            :options="equipmentTypeOptions"
          />
          <MultiSelect
            v-model="filters.equipment_subtype_id"
            :label="t('cards.fields.equipmentSubtype')"
            :placeholder="t('cards.filters.allEquipmentSubtypes')"
            :options="equipmentSubtypeOptions"
          />
        </template>
        <template v-if="showAttack">
          <!-- Orden canónico: rango · tipo · subtipo · área -->
          <MultiSelect
            v-model="filters.attack_range_id"
            :label="t('cards.fields.attackRange')"
            :placeholder="t('cards.filters.allAttackRanges')"
            :options="attackRangeOptions"
          />
          <MultiSelect
            v-model="filters.attack_type"
            :label="t('cards.fields.attackType')"
            :placeholder="t('cards.filters.allAttackTypes')"
            :options="attackTypeOptions"
          />
          <MultiSelect
            v-model="filters.attack_subtype_id"
            :label="t('cards.fields.attackSubtype')"
            :placeholder="t('cards.filters.allAttackSubtypes')"
            :options="attackSubtypeOptions"
          />
          <MultiSelect
            v-model="filters.area"
            :label="t('cards.fields.areaChip')"
            :placeholder="t('cards.filters.areaAll')"
            :options="areaOptions"
          />
        </template>
        <BaseButton variant="text" type="button" @click="clearFilters">
          <template #icon><FunnelX :size="14" /></template>
          {{ t('common.filters.clear') }}
        </BaseButton>
      </template>

      <template #meta>
        <!-- Identidad en DOS filas, como en héroes: la facción (enlace a su
             single) y debajo el coste + tipado (cada término enlaza a su
             definición: el index de su taxonomía con el término seleccionado) -->
        <div v-if="selected" class="cards__identity">
          <p class="manager-detail__meta">
            <RouterLink
              v-if="selected.faction && factionSlug(selected)"
              class="hero-link"
              :title="t('cards.fields.faction')"
              :to="{ name: 'faction-single', params: { slug: factionSlug(selected) } }"
            >
              {{ tr(selected.faction.name) }}
            </RouterLink>
            <span v-else>{{
              selected.faction ? tr(selected.faction.name) : t('cards.fields.noFaction')
            }}</span>
          </p>
          <!-- Coste DELANTE del tipado; manos y única como texto (la única
               coloreada; sin chips en el panel, regla transversal) -->
          <p class="manager-detail__meta cards__typing">
            <CostDice v-if="selected.cost" :cost="selected.cost" />
            <template v-if="selected.card_type">
              <RouterLink
                class="hero-link"
                :title="t('cards.fields.type')"
                :to="definitionLink('card-types', selected.card_type)"
              >
                {{ tr(selected.card_type.name) }}
              </RouterLink>
            </template>
            <template v-if="selected.card_subtype">
              <span>·</span>
              <RouterLink
                class="hero-link"
                :title="t('cards.fields.subtype')"
                :to="definitionLink('card-subtypes', selected.card_subtype)"
              >
                {{ tr(selected.card_subtype.name) }}
              </RouterLink>
            </template>
            <template v-if="selected.equipment_type">
              <span>·</span>
              <RouterLink
                class="hero-link"
                :title="t('cards.fields.equipmentType')"
                :to="definitionLink('equipment-types', selected.equipment_type)"
              >
                {{ tr(selected.equipment_type.name) }}
              </RouterLink>
            </template>
            <template v-if="selected.equipment_subtype">
              <span>·</span>
              <RouterLink
                class="hero-link"
                :title="t('cards.fields.equipmentSubtype')"
                :to="definitionLink('equipment-subtypes', selected.equipment_subtype)"
              >
                {{ tr(selected.equipment_subtype.name) }}
              </RouterLink>
            </template>
            <span v-if="selected.hands">{{
              t(selected.hands > 1 ? 'cards.fields.twoHands' : 'cards.fields.oneHand')
            }}</span>
            <span v-if="selected.is_unique" class="tinted-unique">{{
              t('cards.state.unique')
            }}</span>
          </p>
        </div>

        <!-- Ataque, con el lenguaje divisoria + kicker del panel; rango y
             subtipo enlazan a su definición -->
        <template v-if="selected && hasAttack(selected)">
          <hr class="manager-panel__divider" />
          <p class="manager-panel__kicker">{{ t('cards.sections.attack') }}</p>
          <p class="manager-detail__meta">
            <AttackLine
              linked
              :range="selected.attack_range"
              :type="selected.attack_type"
              :subtype="selected.attack_subtype"
              :area="selected.area"
            />
          </p>
        </template>

        <!-- Efecto (con la habilidad de héroe integrada), en letra pequeña
             como la pasiva del panel de héroes, sobre las previews -->
        <template v-if="selected && hasEffectContent(selected)">
          <hr class="manager-panel__divider" />
          <p class="manager-panel__kicker">{{ t('cards.sections.effects') }}</p>
          <CardEffect :card="selected" class="cards__panel-effect" />
        </template>
      </template>
    </EntityPanel>
  </div>
</template>
