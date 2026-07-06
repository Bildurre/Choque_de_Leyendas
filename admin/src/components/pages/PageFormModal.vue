<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  BaseCheckbox,
  BaseSelect,
  EditModal,
  ImageUpload,
  TranslatableInput,
  useToast,
} from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'

// Formulario de página (crear/editar) en modal, patrón kontuan.
export interface PageRow {
  id: number
  title: Record<string, string>
  description: Record<string, string>
  slug: Record<string, string>
  meta_title: Record<string, string>
  meta_description: Record<string, string>
  parent_id: number | null
  template: string | null
  background_image: string | null
  is_published: boolean
  is_home: boolean
  is_printable: boolean
  blocks_count?: number
}

const props = defineProps<{
  modelValue: boolean
  page?: PageRow | null
  /** Para el selector de página madre. */
  pages: PageRow[]
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t, te } = useI18n()
const toast = useToast()
const locales = useLocalesStore()

const saving = ref(false)
const title = ref<Record<string, string>>({})
const description = ref<Record<string, string>>({})
const metaTitle = ref<Record<string, string>>({})
const metaDescription = ref<Record<string, string>>({})
const parentId = ref<string>('')
const template = ref('default')
const backgroundImage = ref<string | null>(null)
const isPublished = ref(false)
const isPrintable = ref(false)

/** Sube la imagen de fondo al momento (misma ruta que las de los bloques). */
async function uploadBackground(file: File | null) {
  if (!file) {
    backgroundImage.value = null
    return
  }
  try {
    const form = new FormData()
    form.append('image', file)
    const { data } = await api.post('/admin/content/uploads', form)
    backgroundImage.value = data.url
  } catch {
    toast.danger(t('common.errors.action'))
  }
}

// Plantillas del juego (config del motor): el select solo aparece si hay más
// de una. Etiquetas localizables por convención (pages.templates.{clave}).
const templates = ref<{ key: string; label: string }[]>([])

function templateLabel(tpl: { key: string; label: string }): string {
  return te(`pages.templates.${tpl.key}`) ? t(`pages.templates.${tpl.key}`) : tpl.label
}

watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    title.value = { ...(props.page?.title ?? {}) }
    description.value = { ...(props.page?.description ?? {}) }
    metaTitle.value = { ...(props.page?.meta_title ?? {}) }
    metaDescription.value = { ...(props.page?.meta_description ?? {}) }
    parentId.value = props.page?.parent_id ? String(props.page.parent_id) : ''
    template.value = props.page?.template ?? 'default'
    backgroundImage.value = props.page?.background_image ?? null
    isPublished.value = props.page?.is_published ?? false
    isPrintable.value = props.page?.is_printable ?? false
    if (!templates.value.length) {
      try {
        const { data } = await api.get('/admin/pages/templates')
        templates.value = data.data
      } catch {
        // sin catálogo: el campo no se muestra
      }
    }
  },
)

async function save() {
  saving.value = true
  try {
    const payload = {
      title: title.value,
      description: description.value,
      meta_title: metaTitle.value,
      meta_description: metaDescription.value,
      parent_id: parentId.value ? Number(parentId.value) : null,
      template: template.value,
      background_image: backgroundImage.value,
      is_published: isPublished.value,
      is_printable: isPrintable.value,
    }
    if (props.page) await api.put(`/admin/pages/${props.page.id}`, payload)
    else await api.post('/admin/pages', payload)
    toast.success(t('pages.toast.saved'))
    emit('saved')
    emit('update:modelValue', false)
  } catch {
    toast.danger(t('pages.toast.saveError'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <EditModal
    :model-value="modelValue"
    :title="page ? t('pages.edit') : t('pages.new')"
    :submit-label="t('common.save')"
    :cancel-label="t('common.cancel')"
    :loading="saving"
    @update:model-value="(v) => emit('update:modelValue', v)"
    @submit="save"
  >
    <TranslatableInput
      v-model="title"
      :locales="locales.locales"
      :label="t('pages.fields.title')"
      required
    />
    <TranslatableInput
      v-model="description"
      :locales="locales.locales"
      :label="t('pages.fields.description')"
      type="textarea"
    />
    <BaseSelect
      v-model="parentId"
      :label="t('pages.fields.parent')"
      :options="[
        { value: '', label: '—' },
        ...pages
          .filter((p) => p.id !== page?.id)
          .map((p) => ({
            value: String(p.id),
            label: p.title[locales.current] ?? p.title.es ?? String(p.id),
          })),
      ]"
    />
    <BaseSelect
      v-if="templates.length > 1"
      v-model="template"
      :label="t('pages.fields.template')"
      :options="templates.map((tpl) => ({ value: tpl.key, label: templateLabel(tpl) }))"
    />
    <ImageUpload
      :model-value="null"
      :current-url="backgroundImage"
      :label="t('pages.fields.backgroundImage')"
      :drag-text="t('common.imageDrag')"
      :hint-text="t('pages.fields.backgroundImageHint')"
      @update:model-value="uploadBackground"
      @remove="backgroundImage = null"
    />
    <TranslatableInput
      v-model="metaTitle"
      :locales="locales.locales"
      :label="t('pages.fields.metaTitle')"
    />
    <TranslatableInput
      v-model="metaDescription"
      :locales="locales.locales"
      :label="t('pages.fields.metaDescription')"
      type="textarea"
    />
    <BaseCheckbox v-model="isPublished" :label="t('pages.fields.published')" />
    <BaseCheckbox v-model="isPrintable" :label="t('pages.fields.printable')" />
  </EditModal>
</template>
