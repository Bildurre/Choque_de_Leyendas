<script setup lang="ts">
import { computed } from 'vue'

// Tarjeta CSS de facción (portada del viejo `previews/faction.blade.php` +
// `_faction-preview.scss`): marco 5/7 con el color de la facción, emblema a
// sangre (object-fit cover) y nombre centrado con text-stroke del color.
export interface FactionCardData {
  name: string
  color: string
  text_is_dark: boolean
  icon: string | null
}

const props = defineProps<{ faction: FactionCardData }>()

const style = computed(() => ({
  '--faction-color': props.faction.color,
  '--faction-text': props.faction.text_is_dark ? '#000000' : '#ffffff',
}))
</script>

<template>
  <article class="faction-card" :style="style">
    <div v-if="faction.icon" class="faction-card__icon">
      <img :src="faction.icon" :alt="faction.name" loading="lazy" />
    </div>
    <div class="faction-card__content">
      <h3 class="faction-card__name">{{ faction.name }}</h3>
    </div>
  </article>
</template>
