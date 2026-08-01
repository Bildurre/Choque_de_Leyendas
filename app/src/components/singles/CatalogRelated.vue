<script setup lang="ts">
import { ref, watch } from 'vue'
import { BlockRelated, type CatalogItem } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'

// "Relateds" de los singles de carta y héroe: mismo componente BlockRelated
// del motor — idéntico markup y ajustes que el bloque related del CRM
// (título h2 + botón secundario al índice, justificado, ancho wide, fondo
// de tarjeta DINÁMICO token:surface resuelto por BlockShell; los
// enlaces salen del catalogRoutes provisto en main.ts) — con los datos
// resueltos aquí contra el catálogo público en modo random y excluyendo la
// entidad actual — ALEATORIO por visita, mismo criterio que el endpoint del
// motor (inRandomOrder + exclude, patrón del viejo).
const props = withDefaults(
  defineProps<{
    catalogKey: string
    excludeId: number
    /** Título (h2) de la cabecera, como el bloque related del CRM. */
    title: string
    buttonLabel: string
    count?: number
  }>(),
  // 6 como el bloque related del CRM: la rejilla compact del motor decide
  // cuántos enseña por tramo (4 en 2×2 → 6 en 3×2 → 4 en 4×1 → 5 en 5×1).
  { count: 6 },
)

const locales = useLocalesStore()
const items = ref<CatalogItem[]>([])

async function load() {
  try {
    const { data } = await api.get(`/catalog/${props.catalogKey}`, {
      params: { mode: 'random', count: props.count, exclude: props.excludeId },
    })
    items.value = data.data
  } catch {
    items.value = []
  }
}

// Recarga al cambiar de entidad o de idioma (nombres y slugs localizados).
watch([() => props.excludeId, () => locales.current], load, { immediate: true })
</script>

<template>
  <BlockRelated
    v-if="items.length"
    :settings="{
      title,
      with_button: true,
      button_label: buttonLabel,
      align: 'justify',
      width: 'wide',
      background: 'token:surface',
    }"
    :data="{ key: catalogKey, items }"
  />
</template>
