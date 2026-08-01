<script setup lang="ts">
import { computed, type CSSProperties } from 'vue'

// Cabecera de las páginas no-CRM (índices, descargas, herramientas y los
// singles migrados al lenguaje de bloques — ver blockHeader del registry): el
// MISMO markup y clases que el bloque "header" del CRM (BlockHeader +
// BlockShell del motor, _blocks.scss, que la app ya importa vía
// shared-components): así hereda su tipografía (título $fs-28 compacto y
// subtítulo $fs-16 atenuado) y su fondo tintado, y las vistas abren con
// el mismo estilo que una página del CRM. El tinte se calcula como en
// BlockShell (--block-bg = color-mix del color sobre --block-tint, 15%
// por defecto), con el gris pizarra #64748B como color por defecto.
// Slots opcionales para contenido extra dentro del bloque: `top` antes del
// título (p. ej. el «volver» de los singles) y el default después.
const props = withDefaults(
  defineProps<{
    title: string
    subtitle?: string
    /** Color base del tinte de fondo del bloque. */
    background?: string
  }>(),
  { background: '#64748B' },
)

const style = computed<CSSProperties>(() => ({
  '--block-bg': `color-mix(in srgb, ${props.background} var(--block-tint, 15%), transparent)`,
}))
</script>

<template>
  <section class="block block--w-wide block--align-justify block--header" :style="style">
    <div class="block__inner">
      <slot name="top" />
      <h1 class="block__title">{{ title }}</h1>
      <p v-if="subtitle" class="block__subtitle">{{ subtitle }}</p>
      <slot />
    </div>
  </section>
</template>
