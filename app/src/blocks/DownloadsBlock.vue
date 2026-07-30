<script setup lang="ts">
import { defineAsyncComponent } from 'vue'
import { BlockShell } from '@edc-motor/ui'

// Bloque «Descargas» del CRM: monta el apartado público de Descargas (los
// PDF permanentes agrupados por tipo + "tu colección") reutilizando el
// MISMO contenido extraído que la vista de descargas. Sin configuración
// propia (solo los campos comunes del envoltorio). En diferido: chunk
// compartido con la vista.
const DownloadsContent = defineAsyncComponent(() => import('@/components/DownloadsContent.vue'))

defineProps<{
  settings: Record<string, unknown>
  data: Record<string, unknown>
}>()
</script>

<template>
  <BlockShell :settings="settings" class="block--downloads">
    <!-- El contenedor .downloads (de la vista) aporta el layout de _downloads.scss -->
    <div class="downloads">
      <DownloadsContent />
    </div>
  </BlockShell>
</template>
