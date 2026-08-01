<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { BlockRelated, BlockShell, type CatalogItem } from '@edc-motor/ui'
import FactionCard, { type FactionCardData } from '@/components/FactionCard.vue'
import { sectionDetailRoute, sectionIndexRoute } from '@/entities/singleRoutes'
import { useLocalesStore } from '@/stores/locales'

// Bloque `related` del CRM en ESTE juego: para las entidades del registry
// de previews delega tal cual en el BlockRelated del motor (PNG +
// catalogRoutes); para FACCIÓN (clave añadida por GameRelatedBlock de la
// api: sin preview PNG) pinta su TARJETA CSS — la faction-card del índice
// en la variante compacta del related (mismas clases que CssCardsRelated
// de los singles: block--css-related + css-related-grid), cada una
// enlazada a su single y con el botón opcional al índice de facciones.
interface FactionItem extends FactionCardData {
  id: number
  slug: string | null
}

const props = withDefaults(
  defineProps<{
    settings: Record<string, unknown>
    data?: { key?: string | null; items?: (CatalogItem | FactionItem)[] }
  }>(),
  { data: () => ({}) },
)

const locales = useLocalesStore()

const isFaction = computed(() => props.data?.key === 'faction')

// Las 6 que manda la api (como el resto de entidades del related): es la
// rejilla CSS en contexto related quien decide cuántas enseña por tramo
// (4 en 2×2 → 6 en 3×2 → 4 en 4×1 → 5 en 5×1, contrato del preview-grid
// compact del motor).
const factions = computed(
  () => (isFaction.value ? (props.data?.items ?? []).slice(0, 6) : []) as FactionItem[],
)

const indexRoute = computed(() =>
  props.settings.with_button ? sectionIndexRoute('factions', locales.current) : null,
)

function itemRoute(item: FactionItem) {
  return sectionDetailRoute('factions', item.slug, locales.current)
}

// Mismo criterio que BlockRelated del motor: el texto del botón sale del
// bloque (button_label, translatable) con el default del motor detrás.
const buttonLabel = computed(() => String(props.settings.button_label ?? '') || 'Ver todos')
</script>

<template>
  <!-- Facción: tarjetas CSS compactas (sin PNG), enlazadas a su single -->
  <BlockShell v-if="isFaction" :settings="settings" class="block--related block--css-related">
    <div class="block__related-header">
      <div class="block__related-titles">
        <h2 v-if="settings.title" class="block__title">{{ settings.title }}</h2>
        <p v-if="settings.subtitle" class="block__subtitle">{{ settings.subtitle }}</p>
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
        v-for="item in factions"
        :key="item.id"
        class="css-related-grid__item"
        v-bind="itemRoute(item) ? { to: itemRoute(item) } : {}"
      >
        <FactionCard :faction="item" />
      </component>
    </div>
  </BlockShell>

  <!-- Resto de entidades: el BlockRelated del motor, tal cual -->
  <BlockRelated
    v-else
    :settings="settings"
    :data="data as { key?: string | null; items?: CatalogItem[] }"
  />
</template>
