<script setup lang="ts">
import type { CounterRenderData } from '../types'

// Contador recortable (300x300 en /_render, 25 mm impreso): port fiel del PDF
// del viejo (pdfs/cut-out-counters.blade.php): ficha redonda con la imagen
// contenida y el nombre en mayúsculas sobre una pastilla con anillo azul
// (boon) o rojo (bane). El icono boon/bane llega resuelto en item.icons
// (url|null): si falta se omite, NUNCA se incrusta un asset local. El efecto
// no se pinta (a 25 mm es ilegible; el viejo tampoco lo imprimía).
defineProps<{ item: CounterRenderData; locale: string }>()
</script>

<template>
  <div class="game-counter" :class="`game-counter--${item.type}`">
    <img v-if="item.image" class="game-counter__art" :src="item.image" alt="" />
    <img
      v-if="item.icons[item.type]"
      class="game-counter__badge"
      :src="item.icons[item.type]!"
      alt=""
    />
    <span class="game-counter__name">{{ item.name }}</span>
  </div>
</template>
