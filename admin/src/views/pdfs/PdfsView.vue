<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PdfManager } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { usePdfLabels } from '@/lib/pdfLabels'

// Sección PDF del admin: TODO el catálogo de exports del juego se gestiona
// aquí (el gestor lee /admin/pdfs/exports); nada en los detalles.
const { t } = useI18n()
const labels = usePdfLabels()

// Nombre traducido de cada export registrado en el AppServiceProvider:
// añade una entrada (y su clave pdfs.types.* en i18n) por cada
// Pdfs::register(...) de tu juego. 'pages' lo trae el motor.
const typeLabels = computed<Record<string, string>>(() => ({
  pages: t('pdfs.types.pages'),
}))
</script>

<template>
  <div class="pdfs-view">
    <PdfManager :api="api" :labels="labels" :type-labels="typeLabels" />
  </div>
</template>
