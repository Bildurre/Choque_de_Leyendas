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
    underline: t('editor.underline'),
    heading2: t('editor.heading2'),
    heading3: t('editor.heading3'),
    heading4: t('editor.heading4'),
    heading5: t('editor.heading5'),
    bulletList: t('editor.bulletList'),
    orderedList: t('editor.orderedList'),
    blockquote: t('editor.blockquote'),
    indent: t('editor.indent'),
    outdent: t('editor.outdent'),
    link: t('editor.link'),
    unlink: t('editor.unlink'),
    linkUrl: t('editor.linkUrl'),
    linkApply: t('editor.linkApply'),
    insertTable: t('editor.insertTable'),
    addRow: t('editor.addRow'),
    removeRow: t('editor.removeRow'),
    addColumn: t('editor.addColumn'),
    removeColumn: t('editor.removeColumn'),
    toggleHeaderRow: t('editor.toggleHeaderRow'),
    deleteTable: t('editor.deleteTable'),
    undo: t('editor.undo'),
    redo: t('editor.redo'),
    insertIcon: t('editor.insertIcon'),
    editHtml: t('editor.editHtml'),
  }))
}
