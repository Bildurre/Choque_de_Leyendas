<script setup lang="ts">
// Bloque de información de los singles del admin (mismo patrón que el
// InfoBlock de la app, pero con el estilo "solo borde" del admin): título
// + contenido libre y un slot opcional de acciones en la cabecera (p. ej.
// el botón "Editar" de las cards del single de mazo). Los estilos viven en
// components/_info-block.scss.
defineProps<{ title: string }>()

defineSlots<{
  default?: () => unknown
  /** Acciones a la derecha del título (opcional; sin él, nada cambia). */
  actions?: () => unknown
}>()
</script>

<template>
  <section class="info-block">
    <div v-if="$slots.actions" class="info-block__head">
      <h2 class="info-block__title">{{ title }}</h2>
      <slot name="actions" />
    </div>
    <h2 v-else class="info-block__title">{{ title }}</h2>
    <slot />
  </section>
</template>
