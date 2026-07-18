<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { setActiveSlugMap } from '@/router'
import { ArrowLeft, Eye, EyeOff, Minus, Plus, Save, SquarePen, X } from '@lucide/vue'
import { useResource, EmptyState } from '@edc-motor/admin-kit'
import { BaseButton, BaseInput, useToast } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'
import { usePageCrumb } from '@/composables/usePageCrumb'
import type {
  DeckAttributesConfig,
  DeckCardItem,
  DeckHeroItem,
  DeckPublishError,
  FactionDeck,
  Translations,
} from '@juego/shared'
import FactionDeckFormModal from '@/components/faction-decks/FactionDeckFormModal.vue'
import CostDice from '@/components/game/CostDice.vue'

// Single editora del mazo (patrón página+bloques): cabecera con límites del
// modo y contadores en vivo + panel de cartas (copias) + panel de héroes.
// Guardar borradores es libre; los límites solo avisan (el servidor decide
// al publicar).
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const locales = useLocalesStore()
const { find } = useResource<FactionDeck>(api, '/admin/faction-decks')

const deck = ref<FactionDeck | null>(null)
const config = ref<DeckAttributesConfig | null>(null)
const loading = ref(true)
const saving = ref(false)
const dirty = ref(false)
const formOpen = ref(false)
const publishErrors = ref<DeckPublishError[]>([])

// Estado editable (se guarda con los dos PUT)
const cards = ref<DeckCardItem[]>([])
const heroes = ref<DeckHeroItem[]>([])

function tr(obj: Translations | null | undefined): string {
  return (
    obj?.[locales.current] || obj?.[locales.defaultLocale] || Object.values(obj || {})[0] || '—'
  )
}
const slug = computed(() => route.params.slug as string)

async function load() {
  loading.value = true
  try {
    deck.value = await find(slug.value)
    cards.value = (deck.value.cards ?? []).map((c) => ({ ...c }))
    heroes.value = (deck.value.heroes ?? []).map((h) => ({ ...h }))
    dirty.value = false
    publishErrors.value = []
    setActiveSlugMap(deck.value?.slug ?? null) // DC-11: slug localizado al cambiar idioma
    await loadConfig()
  } catch {
    deck.value = null
  } finally {
    loading.value = false
  }
}

/** Límites del modo del mazo (data puede ser null: sin límites). */
async function loadConfig() {
  config.value = null
  if (deck.value?.game_mode_id == null) return
  try {
    const { data } = await api.get(
      `/admin/deck-attributes-configurations/for-mode/${deck.value.game_mode_id}`,
    )
    config.value = data.data
  } catch {
    config.value = null
  }
}

// --- Contadores en vivo ---
const totalCopies = computed(() => cards.value.reduce((sum, c) => sum + c.copies, 0))
const uniqueCards = computed(() => cards.value.length)
const totalHeroes = computed(() => heroes.value.length)

// Avisos de límite en cliente (mismas claves que valida el servidor al publicar)
const warnings = computed<DeckPublishError[]>(() => {
  const cfg = config.value
  if (!cfg) return []
  const out: DeckPublishError[] = []
  if (totalCopies.value < cfg.min_cards) {
    out.push({
      key: 'factionDecks.validation.minCards',
      params: { min: cfg.min_cards, total: totalCopies.value },
    })
  }
  if (totalCopies.value > cfg.max_cards) {
    out.push({
      key: 'factionDecks.validation.maxCards',
      params: { max: cfg.max_cards, total: totalCopies.value },
    })
  }
  const exceeded = cards.value.filter((c) => c.copies > cfg.max_copies_per_card).length
  if (exceeded > 0) {
    out.push({
      key: 'factionDecks.validation.maxCopies',
      params: { max: cfg.max_copies_per_card, count: exceeded },
    })
  }
  if (cfg.required_heroes > 0 && totalHeroes.value !== cfg.required_heroes) {
    out.push({
      key: 'factionDecks.validation.requiredHeroes',
      params: { required: cfg.required_heroes, total: totalHeroes.value },
    })
  }
  return out
})

// --- Edición de cartas del mazo ---
function addCard(item: {
  id: number
  name: Translations
  cost?: string | null
  image?: string | null
}) {
  const existing = cards.value.find((c) => c.id === item.id)
  if (existing) {
    existing.copies += 1
  } else {
    cards.value.push({
      id: item.id,
      name: item.name,
      cost: item.cost ?? null,
      image: item.image ?? null,
      copies: 1,
    })
  }
  dirty.value = true
}
function stepCopies(card: DeckCardItem, delta: number) {
  card.copies = Math.max(1, card.copies + delta)
  dirty.value = true
}
function removeCard(card: DeckCardItem) {
  cards.value = cards.value.filter((c) => c.id !== card.id)
  dirty.value = true
}

// --- Edición de héroes del mazo ---
function addHero(item: { id: number; name: Translations; image?: string | null }) {
  if (heroes.value.some((h) => h.id === item.id)) return
  heroes.value.push({ id: item.id, name: item.name, image: item.image ?? null })
  dirty.value = true
}
function removeHero(hero: DeckHeroItem) {
  heroes.value = heroes.value.filter((h) => h.id !== hero.id)
  dirty.value = true
}

// --- Buscadores para añadir (cartas y héroes, paginados en servidor) ---
interface SearchResult {
  id: number
  name: Translations
  cost?: string | null
  image?: string | null
}

function useSearch(resource: string) {
  const query = ref('')
  const results = ref<SearchResult[]>([])
  const page = ref(1)
  const lastPage = ref(1)
  const searching = ref(false)

  async function run(toPage = 1) {
    searching.value = true
    try {
      const { data } = await api.get(resource, {
        params: { search: query.value, page: toPage },
      })
      results.value = data.data
      page.value = data.meta?.current_page ?? 1
      lastPage.value = data.meta?.last_page ?? 1
    } catch {
      toast.danger(t('common.errors.load'))
    } finally {
      searching.value = false
    }
  }

  let timer: ReturnType<typeof setTimeout> | null = null
  watch(query, () => {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => run(1), 250)
  })
  onBeforeUnmount(() => {
    if (timer) clearTimeout(timer)
  })

  return { query, results, page, lastPage, searching, run }
}

const cardSearch = useSearch('/admin/cards')
const heroSearch = useSearch('/admin/heroes')

// --- Guardar (los dos PUT) y publicar ---
async function save() {
  if (!deck.value) return
  saving.value = true
  try {
    await api.put(`/admin/faction-decks/${slug.value}/cards`, {
      items: cards.value.map((c) => ({ card_id: c.id, copies: c.copies })),
    })
    const { data } = await api.put(`/admin/faction-decks/${slug.value}/heroes`, {
      hero_ids: heroes.value.map((h) => h.id),
    })
    deck.value = data.data
    cards.value = (deck.value?.cards ?? []).map((c) => ({ ...c }))
    heroes.value = (deck.value?.heroes ?? []).map((h) => ({ ...h }))
    dirty.value = false
    toast.success(t('factionDecks.toast.deckSaved'))
  } catch {
    toast.danger(t('factionDecks.toast.saveError'))
  } finally {
    saving.value = false
  }
}

/** Publicar valida en servidor: el 422 trae errors.deck localizable. */
async function togglePublished() {
  if (!deck.value) return
  publishErrors.value = []
  try {
    const { data } = await api.post(`/admin/faction-decks/${slug.value}/toggle-published`)
    const wasPublished = deck.value.is_published
    deck.value = { ...deck.value, ...data.data }
    toast.success(
      wasPublished ? t('factionDecks.toast.unpublished') : t('factionDecks.toast.published'),
    )
  } catch (e) {
    const errs = (e as { response?: { data?: { errors?: { deck?: DeckPublishError[] } } } })
      ?.response?.data?.errors?.deck
    if (Array.isArray(errs) && errs.length) {
      publishErrors.value = errs
      toast.danger(t('factionDecks.toast.publishInvalid'))
    } else {
      toast.danger(t('common.errors.action'))
    }
  }
}

async function onSaved() {
  await load()
}

onMounted(async () => {
  await locales.load()
  await load()
})

// El nombre del single como último tramo de la breadcrumb (se actualiza si
// cambia el locale de contenido) y fuera al salir de la vista.
const crumb = usePageCrumb()
watch(
  [deck, () => locales.current],
  () => {
    if (deck.value) crumb.set(tr(deck.value.name))
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  crumb.clear()
  setActiveSlugMap(null)
})
</script>

<template>
  <div v-if="deck" class="deck-editor">
    <div class="single__bar">
      <BaseButton variant="text" @click="router.push({ name: 'faction-decks' })">
        <template #icon><ArrowLeft :size="16" /></template>
        {{ t('factionDecks.title') }}
      </BaseButton>
      <div class="deck-editor__bar-actions">
        <BaseButton variant="info" @click="formOpen = true">
          <template #icon><SquarePen :size="16" /></template>
          {{ t('common.actions.edit') }}
        </BaseButton>
        <BaseButton variant="warning" @click="togglePublished">
          <template #icon>
            <component :is="deck.is_published ? EyeOff : Eye" :size="16" />
          </template>
          {{ deck.is_published ? t('common.actions.unpublish') : t('common.actions.publish') }}
        </BaseButton>
        <BaseButton variant="success" :disabled="!dirty || saving" @click="save">
          <template #icon><Save :size="16" /></template>
          {{ t('factionDecks.single.save') }}
        </BaseButton>
      </div>
    </div>

    <!-- Cabecera: nombre + límites del modo + contadores en vivo -->
    <header class="deck-editor__header">
      <div class="deck-editor__identity">
        <div class="deck-editor__emblem">
          <img v-if="deck.image" :src="deck.image" alt="" />
          <span v-else class="deck-editor__mono">{{ tr(deck.name).charAt(0) }}</span>
        </div>
        <div>
          <h1>{{ tr(deck.name) }}</h1>
          <!-- Sin chips en los singles (regla transversal): texto coloreado -->
          <p class="deck-editor__meta-line">
            <span v-if="deck.is_published" class="deck-editor__state is-published">{{
              t('factionDecks.state.published')
            }}</span>
            <span v-else class="deck-editor__state">{{ t('factionDecks.state.draft') }}</span>
            <span v-if="deck.game_mode">{{ tr(deck.game_mode.name) }}</span>
            <span
              v-for="faction in deck.factions ?? []"
              :key="faction.id"
              :style="faction.color ? { color: faction.color } : undefined"
            >
              {{ tr(faction.name) }}
            </span>
          </p>
        </div>
      </div>

      <dl class="deck-editor__stats">
        <div v-if="config">
          <dt>{{ t('factionDecks.single.modeLimits') }}</dt>
          <dd>
            {{
              t('factionDecks.single.limitsLine', {
                min: config.min_cards,
                max: config.max_cards,
                copies: config.max_copies_per_card,
                heroes: config.required_heroes,
              })
            }}
          </dd>
        </div>
        <div>
          <dt>{{ t('factionDecks.single.cardsCount') }}</dt>
          <dd>
            {{ totalCopies }}
            <small>({{ t('factionDecks.single.uniqueCards', { count: uniqueCards }) }})</small>
          </dd>
        </div>
        <div>
          <dt>{{ t('factionDecks.single.heroesCount') }}</dt>
          <dd>{{ totalHeroes }}</dd>
        </div>
      </dl>
    </header>

    <!-- Errores de publicación del servidor (bloquean publicar, no guardar) -->
    <ul v-if="publishErrors.length" class="deck-editor__errors">
      <li v-for="(err, i) in publishErrors" :key="i">{{ t(err.key, err.params ?? {}) }}</li>
    </ul>

    <!-- Avisos de límite en vivo (no bloquean: el servidor decide al publicar) -->
    <ul v-if="warnings.length" class="deck-editor__warnings">
      <li v-for="(w, i) in warnings" :key="i">{{ t(w.key, w.params ?? {}) }}</li>
    </ul>

    <div class="deck-editor__panels">
      <!-- Panel de cartas del mazo -->
      <section class="deck-editor__panel">
        <h2 class="single__section">{{ t('factionDecks.single.cardsTitle') }}</h2>

        <EmptyState v-if="!cards.length" :title="t('factionDecks.single.noCards')" />
        <ul v-else class="deck-editor__rows">
          <li v-for="card in cards" :key="card.id" class="deck-editor__row">
            <span class="deck-editor__row-media">
              <img v-if="card.image" :src="card.image" alt="" />
            </span>
            <span class="deck-editor__row-name">{{ tr(card.name) }}</span>
            <span class="deck-editor__row-cost">
              <CostDice v-if="card.cost" :cost="card.cost" />
              <template v-else>—</template>
            </span>
            <span
              class="deck-editor__copies"
              :class="{ 'is-over': config && card.copies > config.max_copies_per_card }"
            >
              <button
                type="button"
                class="deck-editor__step"
                :disabled="card.copies <= 1"
                :aria-label="t('factionDecks.single.fewerCopies')"
                @click="stepCopies(card, -1)"
              >
                <Minus :size="14" />
              </button>
              <span class="deck-editor__copies-value">{{ card.copies }}</span>
              <button
                type="button"
                class="deck-editor__step"
                :aria-label="t('factionDecks.single.moreCopies')"
                @click="stepCopies(card, 1)"
              >
                <Plus :size="14" />
              </button>
            </span>
            <button
              type="button"
              class="deck-editor__remove"
              :aria-label="t('factionDecks.single.removeCard')"
              @click="removeCard(card)"
            >
              <X :size="14" />
            </button>
          </li>
        </ul>

        <!-- Buscador para añadir cartas (paginado en servidor) -->
        <div class="deck-editor__search">
          <BaseInput
            v-model="cardSearch.query.value"
            :label="t('factionDecks.single.addCards')"
            :placeholder="t('factionDecks.single.searchCards')"
          />
          <ul v-if="cardSearch.results.value.length" class="deck-editor__results">
            <li v-for="item in cardSearch.results.value" :key="item.id">
              <span class="deck-editor__row-name">{{ tr(item.name) }}</span>
              <span class="deck-editor__row-cost">
                <CostDice v-if="item.cost" :cost="item.cost" />
                <template v-else>—</template>
              </span>
              <BaseButton variant="text" @click="addCard(item)">
                <template #icon><Plus :size="14" /></template>
                {{ t('factionDecks.single.add') }}
              </BaseButton>
            </li>
          </ul>
          <p
            v-else-if="cardSearch.query.value && !cardSearch.searching.value"
            class="deck-editor__no-results"
          >
            {{ t('common.empty') }}
          </p>
          <div v-if="cardSearch.lastPage.value > 1" class="deck-editor__pager">
            <BaseButton
              variant="text"
              :disabled="cardSearch.page.value <= 1"
              @click="cardSearch.run(cardSearch.page.value - 1)"
            >
              {{ t('factionDecks.single.prev') }}
            </BaseButton>
            <span>{{ cardSearch.page.value }} / {{ cardSearch.lastPage.value }}</span>
            <BaseButton
              variant="text"
              :disabled="cardSearch.page.value >= cardSearch.lastPage.value"
              @click="cardSearch.run(cardSearch.page.value + 1)"
            >
              {{ t('factionDecks.single.next') }}
            </BaseButton>
          </div>
        </div>
      </section>

      <!-- Panel de héroes del mazo -->
      <section class="deck-editor__panel">
        <h2 class="single__section">{{ t('factionDecks.single.heroesTitle') }}</h2>

        <EmptyState v-if="!heroes.length" :title="t('factionDecks.single.noHeroes')" />
        <ul v-else class="deck-editor__rows">
          <li v-for="hero in heroes" :key="hero.id" class="deck-editor__row">
            <span class="deck-editor__row-media">
              <img v-if="hero.image" :src="hero.image" alt="" />
            </span>
            <span class="deck-editor__row-name">{{ tr(hero.name) }}</span>
            <button
              type="button"
              class="deck-editor__remove"
              :aria-label="t('factionDecks.single.removeHero')"
              @click="removeHero(hero)"
            >
              <X :size="14" />
            </button>
          </li>
        </ul>

        <!-- Buscador para añadir héroes (paginado en servidor) -->
        <div class="deck-editor__search">
          <BaseInput
            v-model="heroSearch.query.value"
            :label="t('factionDecks.single.addHeroes')"
            :placeholder="t('factionDecks.single.searchHeroes')"
          />
          <ul v-if="heroSearch.results.value.length" class="deck-editor__results">
            <li v-for="item in heroSearch.results.value" :key="item.id">
              <span class="deck-editor__row-name">{{ tr(item.name) }}</span>
              <BaseButton
                variant="text"
                :disabled="heroes.some((h) => h.id === item.id)"
                @click="addHero(item)"
              >
                <template #icon><Plus :size="14" /></template>
                {{ t('factionDecks.single.add') }}
              </BaseButton>
            </li>
          </ul>
          <p
            v-else-if="heroSearch.query.value && !heroSearch.searching.value"
            class="deck-editor__no-results"
          >
            {{ t('common.empty') }}
          </p>
          <div v-if="heroSearch.lastPage.value > 1" class="deck-editor__pager">
            <BaseButton
              variant="text"
              :disabled="heroSearch.page.value <= 1"
              @click="heroSearch.run(heroSearch.page.value - 1)"
            >
              {{ t('factionDecks.single.prev') }}
            </BaseButton>
            <span>{{ heroSearch.page.value }} / {{ heroSearch.lastPage.value }}</span>
            <BaseButton
              variant="text"
              :disabled="heroSearch.page.value >= heroSearch.lastPage.value"
              @click="heroSearch.run(heroSearch.page.value + 1)"
            >
              {{ t('factionDecks.single.next') }}
            </BaseButton>
          </div>
        </div>
      </section>
    </div>

    <FactionDeckFormModal v-model="formOpen" mode="edit" :target-slug="slug" @saved="onSaved" />
  </div>
  <p v-else-if="!loading" class="single__empty">{{ t('common.empty') }}</p>
</template>
