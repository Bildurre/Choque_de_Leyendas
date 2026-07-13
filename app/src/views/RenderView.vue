<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/lib/api'
import { renderRegistry } from '@/render/registry'

// Ruta "desnuda" para la captura a PNG (doc 01, DC-04): monta SOLO el
// componente visual de la entidad, con los datos que la API entrega a cambio
// del token de servicio que emitió el backend al lanzar Browsershot.
const route = useRoute()

const entity = computed(() => String(route.params.entity))
const id = computed(() => String(route.params.id))
const locale = computed(() => String(route.query.locale || 'es'))

const item = ref<Record<string, unknown> | null>(null)
const failed = ref(false)

const component = computed(() => renderRegistry[entity.value] ?? null)

declare global {
  interface Window {
    __bgmRenderReady?: boolean
    __bgmRenderError?: string
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/render/${entity.value}/${id.value}`, {
      params: { locale: locale.value, token: route.query.token },
    })
    item.value = data.data
  } catch {
    failed.value = true
    window.__bgmRenderError = 'render-data-failed'
    return
  }

  // Señal para Browsershot (waitForFunction): componente montado, imágenes
  // y fuentes cargadas.
  await nextTick()
  await document.fonts.ready
  const images = Array.from(document.images).map((img) =>
    img.complete
      ? Promise.resolve()
      : new Promise((resolve) => {
          img.onload = img.onerror = resolve
        }),
  )
  await Promise.all(images)
  window.__bgmRenderReady = true
})
</script>

<template>
  <div class="render-stage">
    <component :is="component" v-if="component && item" :item="item" :locale="locale" />
  </div>
</template>

<style>
/* La captura omite el fondo por defecto de Chromium (hideBackground en el
   PreviewRenderer del core): para que el PNG salga con alfa de verdad,
   html/body tampoco pintan fondo en esta ruta. El fondo, si lo hay, lo
   decide el componente de cada entidad (cartas y héroes pintan el suyo). */
html:has(.render-stage),
html:has(.render-stage) body {
  background: transparent;
}
</style>
