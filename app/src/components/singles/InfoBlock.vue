<script setup lang="ts">
// Bloque de información de las fichas (x-entity-show.info-block del viejo):
// tarjeta semitransparente con título opcional y contenido libre (sin
// título: la tarjeta de trasfondo/descripción de los singles).
defineProps<{ title?: string }>()
</script>

<template>
  <section class="info-block">
    <h2 v-if="title" class="info-block__title">{{ title }}</h2>
    <slot />
  </section>
</template>
