import { computed, type ComputedRef } from 'vue'
import { useI18n } from 'vue-i18n'
import type { RichTextLabels } from '@edc-motor/ui'

/** Textos traducidos de la barra del editor WYSIWYG (DC-29). */
export function useEditorLabels(): ComputedRef<RichTextLabels> {
  const { t } = useI18n()

  return computed(() => ({
    bold: t('editor.bold'),
    italic: t('editor.italic'),
    strike: t('editor.strike'),
    heading: t('editor.heading'),
    bulletList: t('editor.bulletList'),
    orderedList: t('editor.orderedList'),
    undo: t('editor.undo'),
    redo: t('editor.redo'),
    insertIcon: t('editor.insertIcon'),
    editHtml: t('editor.editHtml'),
  }))
}
