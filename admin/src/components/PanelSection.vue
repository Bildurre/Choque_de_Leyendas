<script setup lang="ts">
// Sección del panel derecho de los gestores: divisoria + kicker + contenido,
// con el MISMO aire entre el título y lo suyo en todas las barras (antes cada
// vista lo montaba a mano y cada una respiraba distinto). Estilos en
// components/_panel-section.scss.
defineProps<{ title: string }>()
</script>

<template>
  <section class="panel-section">
    <hr class="manager-panel__divider" />
    <p class="manager-panel__kicker">{{ title }}</p>
    <div class="panel-section__body">
      <slot />
    </div>
  </section>
</template>
