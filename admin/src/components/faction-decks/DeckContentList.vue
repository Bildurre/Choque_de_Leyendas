<script setup lang="ts">
import { computed } from 'vue'
import { useLocalesStore } from '@/stores/locales'
import type { DeckCardItem, DeckHeroItem, FactionOption, Translations } from '@juego/shared'

// Lista del contenido de un mazo (héroes o cartas), compartida por el panel
// derecho del listado y el single: dot del color de la facción delante del
// nombre (resuelto desde las facciones del propio mazo), nombre enlazado a
// su single y ×copias pegado al nombre cuando toca. Estilos en
// views/_faction-decks.scss (.deck-list).
const props = defineProps<{
  items: (DeckHeroItem | DeckCardItem)[]
  route: 'hero-single' | 'card-single'
  /** Facciones del mazo (id → color/nombre del dot). */
  factions?: FactionOption[]
  /** A dos columnas mientras quepan (la card de cartas del single). */
  columns?: boolean
}>()

const locales = useLocalesStore()

function tr(obj: Translations | null | undefined) {
  return (
    obj?.[locales.current] || obj?.[locales.defaultLocale] || Object.values(obj || {})[0] || '—'
  )
}

/** Slug localizado del elemento (enlace a su single). */
function slugOf(item: { slug?: Translations }): string {
  return item.slug?.[locales.current] || Object.values(item.slug ?? {})[0] || ''
}

const factionById = computed(() => new Map((props.factions ?? []).map((f) => [f.id, f])))

function factionOf(item: DeckHeroItem | DeckCardItem): FactionOption | undefined {
  return item.faction_id ? factionById.value.get(item.faction_id) : undefined
}

function copiesOf(item: DeckHeroItem | DeckCardItem): number {
  return 'copies' in item ? item.copies : 0
}
</script>

<template>
  <ul class="deck-list" :class="{ 'deck-list--columns': columns }">
    <li v-for="item in items" :key="item.id">
      <span
        v-if="factionOf(item)?.color"
        class="deck-list__dot"
        :style="{ background: factionOf(item)?.color }"
        :title="tr(factionOf(item)?.name)"
      />
      <RouterLink
        v-if="slugOf(item)"
        class="hero-link deck-list__name"
        :to="{ name: route, params: { slug: slugOf(item) } }"
        >{{ tr(item.name) }}</RouterLink
      >
      <span v-else class="deck-list__name">{{ tr(item.name) }}</span>
      <span v-if="copiesOf(item) > 1" class="deck-list__copies">×{{ copiesOf(item) }}</span>
    </li>
  </ul>
</template>
