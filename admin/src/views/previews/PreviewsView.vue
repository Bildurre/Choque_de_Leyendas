<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PreviewManager, type PreviewManagerLabels } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'

// Gestor de imágenes (previews PNG): el componente vive en @edc-motor/admin-kit;
// aquí solo se le pasan la API y los textos traducidos (DC-29).
const { t } = useI18n()

// Nombre traducido de cada tipo renderizable (clave del PreviewRegistry):
// añade una entrada por cada Previews::register(...) de tu juego.
const typeLabels = computed<Record<string, string>>(() => ({}))

const labels = computed<PreviewManagerLabels>(() => ({
  refresh: t('previewsManager.refresh'),
  clean: t('previewsManager.clean'),
  total: t('previewsManager.total'),
  generateMissing: t('previewsManager.generateMissing'),
  regenerateAll: t('previewsManager.regenerateAll'),
  deleteAll: t('previewsManager.deleteAll'),
  selectItem: t('previewsManager.selectItem'),
  searchPlaceholder: t('previewsManager.searchPlaceholder'),
  noResults: t('previewsManager.noResults'),
  loadMore: t('previewsManager.loadMore'),
  panelEmpty: t('previewsManager.panelEmpty'),
  detailTitle: t('previewsManager.detailTitle'),
  itemGenerateMissing: t('previewsManager.itemGenerateMissing'),
  itemRegenerate: t('previewsManager.itemRegenerate'),
  itemDelete: t('previewsManager.itemDelete'),
  confirmRegenerateAll: t('previewsManager.confirmRegenerateAll'),
  confirmDeleteAll: t('previewsManager.confirmDeleteAll'),
  confirmDeleteItem: t('previewsManager.confirmDeleteItem'),
  confirmClean: t('previewsManager.confirmClean'),
  confirm: t('previewsManager.confirm'),
  cancel: t('common.cancel'),
  error: t('common.errors.action'),
}))
</script>

<template>
  <div class="previews-view">
    <PreviewManager :api="api" :labels="labels" :type-labels="typeLabels" />
  </div>
</template>
