<script setup lang="ts">
// TEMPORAL: banco de pruebas de los componentes de render (cartas 15 y 89,
// héroes 1 y 2) para inspeccionar su HTML/CSS con las devtools y editar
// packages/shared/scss con hot-reload, sin tokens ni PNG de por medio.
// BORRAR junto con la ruta /test del router y el endpoint /api/test-render.
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { CardRender, HeroRender } from '@juego/shared'
import type { CardRenderData, HeroRenderData } from '@juego/shared'
import { api } from '@/lib/api'

type TestItem =
  | { entity: 'hero'; id: number; item: HeroRenderData }
  | { entity: 'card'; id: number; item: CardRenderData }

const route = useRoute()
const locale = computed(() => (route.query.locale as string) || 'es')
const items = ref<TestItem[]>([])
const failed = ref(false)

onMounted(async () => {
  try {
    const { data } = await api.get('/test-render', { params: { locale: locale.value } })
    items.value = data.data
  } catch {
    failed.value = true
  }
})
</script>

<template>
  <div class="test-render">
    <p v-if="failed" class="test-render__error">
      No se pudieron cargar los datos (¿está el api arrancado y existen los ids?).
    </p>
    <div v-for="it in items" :key="`${it.entity}-${it.id}`" class="test-render__slot">
      <p class="test-render__label">{{ it.entity }} #{{ it.id }}</p>
      <HeroRender v-if="it.entity === 'hero'" :item="it.item" :locale="locale" />
      <CardRender v-else :item="it.item" :locale="locale" />
    </div>
  </div>
</template>

<style scoped>
/* TEMPORAL: todos a tamaño héroe (750px de ancho; el alto lo pone la
   proporción 5/7 del propio componente). */
.test-render {
  display: flex;
  flex-wrap: wrap;
  gap: 24px;
  padding: 24px;
  align-items: flex-start;
}

.test-render__slot {
  width: 750px;
}

/* Tamaño héroe REAL (el del PNG, 750x1050 del RENDER-SPEC): sin esto el
   componente suelto pinta a su ancho por defecto (375px, media carta). El
   ancho fijo va en el MARCO (.game-card-frame, el contenedor cqw real): la
   carta interior es 100%/100% de él. Prueba de fuego: cambiar el ancho de
   la VENTANA del navegador no debe alterar el tamaño de la carta (antes sí:
   los cqw de su propia raíz iban por viewport). */
.test-render__slot :deep(.game-card-frame) {
  width: 750px;
}

.test-render__label {
  margin: 0 0 8px;
  font-family: monospace;
}

.test-render__error {
  font-family: monospace;
  color: #c00;
}
</style>
