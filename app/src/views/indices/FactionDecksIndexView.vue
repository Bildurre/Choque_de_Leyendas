<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/lib/api'
import FactionDeckCard, { type FactionDeckCardData } from '@/components/FactionDeckCard.vue'
import { useIndexPage } from '@/entities/indexPage'

// Índice público de mazos (CONVENTIONS2 §7.5, diseño del viejo §9.4):
// GET /api/faction-decks trae TODOS los mazos publicados y aquí se agrupan
// por modo de juego (las pestañas del viejo, en cliente) con contador.
// Tarjeta CSS con icono, badge de facción/multifacción y totales.
interface DeckRow extends FactionDeckCardData {
  id: number
  slug: string
}

const { t } = useI18n()
const { locales, site, segment, section, canonicalize, applyHead } = useIndexPage()

const items = ref<DeckRow[]>([])
const loading = ref(true)
const activeMode = ref<number | null>(null)

// Modos presentes, con su nº de mazos (orden de aparición del listado).
const modes = computed(() => {
  const map = new Map<number, { id: number; name: string; count: number }>()
  for (const deck of items.value) {
    if (!deck.game_mode) continue
    const entry = map.get(deck.game_mode.id)
    if (entry) entry.count++
    else map.set(deck.game_mode.id, { ...deck.game_mode, count: 1 })
  }
  return [...map.values()]
})

const visible = computed(() =>
  activeMode.value === null
    ? items.value
    : items.value.filter((deck) => deck.game_mode?.id === activeMode.value),
)

// Al (re)cargar, la primera pestaña queda activa (patrón del viejo).
watch(modes, (list) => {
  if (!list.some((mode) => mode.id === activeMode.value)) {
    activeMode.value = list[0]?.id ?? null
  }
})

async function load() {
  if (!section.value || canonicalize()) return
  loading.value = true
  try {
    await site.load() // el head usa documentTitle: sin carreras en el prerender
    const { data } = await api.get('/faction-decks')
    items.value = data.data
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
  applyHead(t(section.value.titleKey))
}

watch([segment, () => locales.current], load, { immediate: true })
</script>

<template>
  <main v-if="section" class="decks-index">
    <h1 class="decks-index__title">{{ t(section.titleKey) }}</h1>

    <!-- Pestañas por modo de juego (client-side), con contador de mazos -->
    <div v-if="modes.length > 1" class="decks-index__tabs" role="tablist">
      <button
        v-for="mode in modes"
        :key="mode.id"
        type="button"
        role="tab"
        class="decks-index__tab"
        :class="{ 'is-active': mode.id === activeMode }"
        :aria-selected="mode.id === activeMode"
        @click="activeMode = mode.id"
      >
        {{ mode.name }}
        <span class="decks-index__tab-count">{{ mode.count }}</span>
      </button>
    </div>

    <p v-if="loading" class="decks-index__loading" role="status">{{ t('catalog.loading') }}</p>
    <p v-else-if="!visible.length" class="decks-index__empty">{{ t('list.empty') }}</p>

    <div v-else class="decks-index__grid">
      <RouterLink
        v-for="deck in visible"
        :key="deck.id"
        class="decks-index__item"
        :to="{
          name: 'entity-detail',
          params: { locale: locales.current, section: segment, slug: deck.slug },
        }"
      >
        <FactionDeckCard :deck="deck" />
      </RouterLink>
    </div>
  </main>
</template>
