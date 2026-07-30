<script setup lang="ts">
import { computed, defineAsyncComponent, type Component } from 'vue'
import { BlockShell } from '@edc-motor/ui'
import { entitySections, type EntitySection } from '@/entities/registry'

// Bloque «Índice de entidad» del CRM: monta el ÍNDICE COMPLETO de la
// sección elegida (búsqueda, filtros de la barra derecha y rejilla con
// paginación doble) reutilizando el MISMO catálogo extraído que la vista
// índice correspondiente — el estado (búsqueda/página/filtros) sigue
// viajando por la query string de la página. La clave guardada en settings
// casa con `entitySections` (entities/registry.ts). Catálogos en diferido:
// no engordan el bundle de la página si el bloque no se usa (chunk
// compartido con la vista índice).
const CardsCatalog = defineAsyncComponent(() => import('@/components/indices/CardsCatalog.vue'))
const HeroesCatalog = defineAsyncComponent(() => import('@/components/indices/HeroesCatalog.vue'))
const FactionsCatalog = defineAsyncComponent(
  () => import('@/components/indices/FactionsCatalog.vue'),
)
const FactionDecksCatalog = defineAsyncComponent(
  () => import('@/components/indices/FactionDecksCatalog.vue'),
)

// Catálogo y clases de contenedor (las de la vista índice, para heredar su
// layout de _indices.scss) por clave de sección.
const catalogs: Record<string, { component: Component; classes: string }> = {
  cards: { component: CardsCatalog, classes: 'catalog-index cards-index' },
  heroes: { component: HeroesCatalog, classes: 'catalog-index heroes-index' },
  factions: { component: FactionsCatalog, classes: 'factions-index' },
  decks: { component: FactionDecksCatalog, classes: 'decks-index' },
}

const props = defineProps<{
  settings: Record<string, unknown>
  data: Record<string, unknown>
}>()

const section = computed<EntitySection | null>(
  () => entitySections.find((s) => s.key === String(props.settings.section ?? '')) ?? null,
)

const catalog = computed(() => (section.value ? (catalogs[section.value.key] ?? null) : null))
</script>

<template>
  <BlockShell :settings="settings" class="block--entity-index">
    <div v-if="section && catalog" :class="catalog.classes">
      <component :is="catalog.component" :section="section" />
    </div>
  </BlockShell>
</template>
