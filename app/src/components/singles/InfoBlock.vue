<script setup lang="ts">
// Bloque de información de las fichas (x-entity-show.info-block del viejo):
// tarjeta semitransparente con título y contenido libre.
defineProps<{ title: string }>()
</script>

<template>
  <section class="info-block">
    <h2 class="info-block__title">{{ title }}</h2>
    <slot />
  </section>
</template>
