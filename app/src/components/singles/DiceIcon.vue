<script setup lang="ts">
// Dado del juego (portado del icon-dice.blade.php del viejo, variante
// monocolor: el coste normalizado solo usa dados R/G/B puros). El color va
// por clase (.icon-dice--red|green|blue) sobre las caras del SVG.
withDefaults(
  defineProps<{
    type?: 'red' | 'green' | 'blue'
    /** Tamaño (clase del viejo): xs 1.25rem, sm 1.5rem, md 2rem, lg 2.5rem. */
    size?: 'xs' | 'sm' | 'md' | 'lg'
  }>(),
  { type: 'red', size: 'md' },
)
</script>

<template>
  <span
    class="icon-dice"
    :class="[`icon-dice--${type}`, size !== 'md' ? `icon-dice--${size}` : '']"
    aria-hidden="true"
  >
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="16 16 168 168" stroke-linejoin="round">
      <polygon
        points="100,180 30,140 30,60 100,100"
        class="icon-dice__face"
        stroke="black"
        stroke-width="2"
      />
      <polygon
        points="100,180 100,100 170,60 170,140"
        class="icon-dice__face"
        stroke="black"
        stroke-width="2"
      />
      <polygon
        points="100,100 30,60 100,20 170,60"
        class="icon-dice__face"
        stroke="black"
        stroke-width="2"
      />
    </svg>
  </span>
</template>
