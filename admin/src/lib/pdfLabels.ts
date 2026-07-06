import { computed, type ComputedRef } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PdfManagerLabels } from '@edc-motor/admin-kit'

/** Textos traducidos del gestor de PDF (DC-29). */
export function usePdfLabels(): ComputedRef<PdfManagerLabels> {
  const { t } = useI18n()

  return computed(() => ({
    refresh: t('pdfs.refresh'),
    generate: t('pdfs.generate'),
    generateMissing: t('pdfs.generateMissing'),
    regenerateAll: t('pdfs.regenerateAll'),
    deleteAll: t('pdfs.deleteAll'),
    download: t('pdfs.download'),
    regenerate: t('pdfs.regenerate'),
    delete: t('pdfs.delete'),
    confirmDelete: t('pdfs.confirmDelete'),
    confirmRegenerateAll: t('pdfs.confirmRegenerateAll'),
    confirmDeleteAll: t('pdfs.confirmDeleteAll'),
    confirm: t('common.confirm'),
    cancel: t('common.cancel'),
    empty: t('pdfs.empty'),
    error: t('common.errors.action'),
    statusPending: t('pdfs.statusPending'),
    statusReady: t('pdfs.statusReady'),
    statusFailed: t('pdfs.statusFailed'),
    detailTitle: t('pdfs.detailTitle'),
    panelEmpty: t('pdfs.panelEmpty'),
    selectSource: t('pdfs.selectSource'),
    searchPlaceholder: t('pdfs.searchPlaceholder'),
    noResults: t('pdfs.noResults'),
    generatedAt: t('pdfs.generatedAt'),
    total: t('previewsManager.total'),
  }))
}
