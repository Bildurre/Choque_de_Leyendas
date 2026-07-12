<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { BlockShell } from '@edc-motor/ui'
import { api } from '@/lib/api'
import FactionCard, { type FactionCardData } from '@/components/FactionCard.vue'
import FactionDeckCard, { type FactionDeckCardData } from '@/components/FactionDeckCard.vue'
import { sectionDetailRoute, sectionIndexRoute } from '@/entities/singleRoutes'
import { useLocalesStore } from '@/stores/locales'

// "Relateds" de los singles para entidades SIN preview PNG (facciones y
// mazos): el bloque `related` del motor solo cubre el registry de previews
// (CONVENTIONS2 §1/§7.3), así que aquí se replica su diseño (cabecera +
// rejilla + botón al índice, _block-relateds.scss del viejo) con las
// tarjetas CSS del juego. Aleatorio EN CLIENTE sobre el índice público,
// excluyendo la entidad actual (patrón del BlockDataService viejo).
interface RelatedRow {
  id: number
  slug: string
  [key: string]: unknown
}

const props = withDefaults(
  defineProps<{
    kind: 'faction' | 'deck'
    excludeId: number
    subtitle: string
    buttonLabel: string
    count?: number
  }>(),
  { count: 4 },
)

const locales = useLocalesStore()
const items = ref<RelatedRow[]>([])

const endpoint = computed(() => (props.kind === 'faction' ? '/factions' : '/faction-decks'))
const sectionKey = computed(() => (props.kind === 'faction' ? 'factions' : 'decks'))

const indexRoute = computed(() => sectionIndexRoute(sectionKey.value, locales.current))

function itemRoute(item: RelatedRow) {
  return sectionDetailRoute(sectionKey.value, item.slug, locales.current)
}

async function load() {
  try {
    const { data } = await api.get(endpoint.value)
    const rows = (data.data as RelatedRow[]).filter((row) => row.id !== props.excludeId)
    // Barajado Fisher-Yates y recorte (aleatorio por visita, como el viejo)
    for (let i = rows.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1))
      ;[rows[i], rows[j]] = [rows[j], rows[i]]
    }
    items.value = rows.slice(0, props.count)
  } catch {
    items.value = []
  }
}

// Recarga al cambiar de entidad o de idioma (nombres y slugs localizados).
watch([() => props.excludeId, () => locales.current], load, { immediate: true })
</script>

<template>
  <BlockShell
    v-if="items.length"
    :settings="{ align: 'left', width: 'wide' }"
    class="block--related block--css-related"
  >
    <div class="block__related-header">
      <div class="block__related-titles">
        <p class="block__subtitle">{{ subtitle }}</p>
      </div>
      <RouterLink
        v-if="indexRoute"
        class="block-button block-button--secondary block__related-button"
        :to="indexRoute"
      >
        {{ buttonLabel }}
      </RouterLink>
    </div>

    <div class="css-related-grid">
      <component
        :is="itemRoute(item) ? RouterLink : 'div'"
        v-for="item in items"
        :key="item.id"
        class="css-related-grid__item"
        v-bind="itemRoute(item) ? { to: itemRoute(item) } : {}"
      >
        <FactionCard v-if="kind === 'faction'" :faction="item as unknown as FactionCardData" />
        <FactionDeckCard v-else :deck="item as unknown as FactionDeckCardData" />
      </component>
    </div>
  </BlockShell>
</template>
