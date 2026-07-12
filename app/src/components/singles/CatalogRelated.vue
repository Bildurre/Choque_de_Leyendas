<script setup lang="ts">
import { ref, watch } from 'vue'
import { BlockRelated, type CatalogItem } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'

// "Relateds" de los singles de carta y héroe: mismo componente BlockRelated
// del motor (los enlaces salen del catalogRoutes provisto en main.ts), con
// los datos resueltos aquí contra el catálogo público en modo random y
// excluyendo la entidad actual — aleatorio por visita, patrón del viejo.
const props = withDefaults(
  defineProps<{
    catalogKey: string
    excludeId: number
    subtitle: string
    buttonLabel: string
    count?: number
  }>(),
  { count: 4 },
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
    :settings="{ subtitle, with_button: true, button_label: buttonLabel, align: 'left' }"
    :data="{ key: catalogKey, items }"
  />
</template>
