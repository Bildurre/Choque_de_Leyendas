<script setup lang="ts">
// Cabecera de las páginas no-CRM (índices, descargas y herramientas): el
// MISMO markup y clases que el bloque "header" del CRM (BlockHeader +
// BlockShell del motor), así hereda su tipografía (título $fs-28 compacto
// y subtítulo $fs-16 atenuado desde motor 0.4.31 — la escala clásica de
// estas cabeceras) y las vistas abren con el mismo estilo que una página
// del CRM. La clase propia `index-header` permite ajustar el respiro
// (padding) sin tocar los bloques reales (_index-header.scss).
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <header class="index-header block block--header block--w-wide block--align-justify">
    <div class="block__inner">
      <h1 class="block__title">{{ title }}</h1>
      <p v-if="subtitle" class="block__subtitle">{{ subtitle }}</p>
    </div>
  </header>
</template>
