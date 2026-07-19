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
// Imagen de fondo DIFERIDA (mismo patrón que ImageUpload/HasImage): la subida
// real al endpoint de contenidos (misma ruta que las de los bloques) no
// viaja hasta el GUARDAR. `originalBackgroundImage` guarda la URL cargada al
// abrir para poder sustituirla (`replaces`) o borrarla si se quita/cambia.
const backgroundImageFile = ref<File | null>(null)
const currentBackgroundImage = ref<string | null>(null)
const originalBackgroundImage = ref<string | null>(null)
const removeBackgroundImage = ref(false)
watch(backgroundImageFile, (file) => {
  if (file) removeBackgroundImage.value = false
})
const isPublished = ref(false)
const isPrintable = ref(false)

function onRemoveBackgroundImage() {
  removeBackgroundImage.value = true
  currentBackgroundImage.value = null
}

/** Resuelve la imagen de fondo final al guardar: sube el fichero pendiente
 *  (sustituyendo la anterior), borra si se quitó, o mantiene la actual. */
async function resolveBackgroundImage(): Promise<string | null> {
  if (backgroundImageFile.value) {
    const form = new FormData()
    form.append('image', backgroundImageFile.value)
    if (originalBackgroundImage.value) form.append('replaces', originalBackgroundImage.value)
    const { data } = await api.post('/admin/content/uploads', form)
    return data.url
  }
  if (removeBackgroundImage.value) {
    if (originalBackgroundImage.value) {
      await api
        .delete('/admin/content/uploads', { data: { url: originalBackgroundImage.value } })
        .catch(() => {})
    }
    return null
  }
  return originalBackgroundImage.value
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
    backgroundImageFile.value = null
    currentBackgroundImage.value = props.page?.background_image ?? null
    originalBackgroundImage.value = props.page?.background_image ?? null
    removeBackgroundImage.value = false
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
    // La imagen de fondo se sube (o borra) aquí, solo al guardar.
    const background_image = await resolveBackgroundImage()
    const payload = {
      title: title.value,
      description: description.value,
      meta_title: metaTitle.value,
      meta_description: metaDescription.value,
      parent_id: parentId.value ? Number(parentId.value) : null,
      template: template.value,
      background_image,
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
      v-model="backgroundImageFile"
      :current-url="currentBackgroundImage"
      :label="t('pages.fields.backgroundImage')"
      :drag-text="t('common.imageDrag')"
      :hint-text="t('pages.fields.backgroundImageHint')"
      @remove="onRemoveBackgroundImage"
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
