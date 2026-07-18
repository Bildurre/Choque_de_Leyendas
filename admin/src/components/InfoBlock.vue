<script setup lang="ts">
// Bloque de información de los singles del admin (mismo patrón que el
// InfoBlock de la app, pero con el estilo "solo borde" del admin): título
// + contenido libre. Los estilos viven en components/_info-block.scss.
defineProps<{ title: string }>()
</script>

<template>
  <section class="info-block">
    <h2 class="info-block__title">{{ title }}</h2>
    <slot />
  </section>
</template>
