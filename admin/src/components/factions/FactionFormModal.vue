<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  EditModal,
  TranslatableInput,
  ImageUpload,
  PaletteColorPicker,
  BaseCheckbox,
  useToast,
} from '@edc-motor/ui'
import { useResource } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useEditorLabels } from '@/lib/editorLabels'
import { useLocalesStore } from '@/stores/locales'
import { useIconsStore } from '@/stores/icons'
import type { Faction } from '@juego/shared'

// Formulario de facción en modal (patrón kontuan): se abre desde el listado
// o desde el single. Trasfondo y cita épica en wysiwyg con iconos del juego.
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  targetSlug?: string | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const icons = useIconsStore()
const { find, createForm, updateForm } = useResource<Faction>(api, '/admin/factions')
const editorLabels = useEditorLabels()

const saving = ref(false)
const image = ref<File | null>(null)
const currentImage = ref<string | null>(null)
const errors = reactive<Record<string, string>>({})

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}
// Traduce cualquier error del backend con clave campo.<locale> al campo raíz.
function mapServerErrors(e: unknown) {
  for (const [k, v] of Object.entries(fieldErrors(e))) {
    if (k === 'name' || k.startsWith('name.')) errors.name = v
    if (k === 'color') errors.color = v
  }
}

const form = reactive<{
  name: Record<string, string>
  lore_text: Record<string, string>
  epic_quote: Record<string, string>
  color: string
  is_published: boolean
}>({ name: {}, lore_text: {}, epic_quote: {}, color: '#888888', is_published: false })

const title = computed(() => (props.mode === 'create' ? t('factions.new') : t('factions.edit')))
// Iconos del juego con URL para el selector del editor.
const iconList = computed(() =>
  icons.icons.filter((i) => i.url).map((i) => ({ name: i.name, url: i.url as string })),
)

function reset() {
  form.name = {}
  form.lore_text = {}
  form.epic_quote = {}
  form.color = '#888888'
  form.is_published = false
  image.value = null
  currentImage.value = null
  clearErrors()
}

// ¿Tiene el nombre algún valor en cualquier idioma?
const hasName = () => Object.values(form.name).some((v) => v && v.trim() !== '')

// Al abrir: en edición carga la facción por slug; en alta limpia.
watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    reset()
    try {
      await Promise.all([locales.load(), icons.load()])
    } catch {
      toast.danger(t('common.errors.load'))
    }
    if (props.mode === 'edit' && props.targetSlug) {
      try {
        const f = await find(props.targetSlug)
        form.name = f.name ?? {}
        form.lore_text = f.lore_text ?? {}
        form.epic_quote = f.epic_quote ?? {}
        form.color = f.color ?? '#888888'
        form.is_published = !!f.is_published
        currentImage.value = f.image ?? null
      } catch {
        toast.danger(t('factions.toast.saveError'))
        emit('update:modelValue', false)
      }
    }
  },
)

function toFormData(): FormData {
  const fd = new FormData()
  for (const [k, v] of Object.entries(form.name)) fd.append(`name[${k}]`, v ?? '')
  for (const [k, v] of Object.entries(form.lore_text)) fd.append(`lore_text[${k}]`, v ?? '')
  for (const [k, v] of Object.entries(form.epic_quote)) fd.append(`epic_quote[${k}]`, v ?? '')
  fd.append('color', form.color)
  fd.append('is_published', form.is_published ? '1' : '0')
  if (image.value) fd.append('image', image.value)
  return fd
}

async function submit() {
  clearErrors()
  // Validación mínima en cliente: evita un 422 innecesario y marca el campo.
  if (!hasName()) {
    errors.name = t('common.required')
    return
  }
  saving.value = true
  try {
    if (props.mode === 'edit' && props.targetSlug) {
      await updateForm(props.targetSlug, toFormData())
      toast.success(t('factions.toast.updated'))
    } else {
      await createForm(toFormData())
      toast.success(t('factions.toast.created'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    mapServerErrors(e)
    toast.danger(t('factions.toast.saveError'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <EditModal
    :model-value="modelValue"
    :title="title"
    :loading="saving"
    :submit-label="t('common.save')"
    :cancel-label="t('common.cancel')"
    @update:model-value="(v: boolean) => emit('update:modelValue', v)"
    @submit="submit"
  >
    <TranslatableInput
      v-model="form.name"
      :locales="locales.locales"
      :label="t('factions.fields.name')"
      required
      :error="errors.name"
    />
    <TranslatableInput
      v-model="form.lore_text"
      :locales="locales.locales"
      :label="t('factions.fields.loreText')"
      type="wysiwyg"
      :icons="iconList"
      :rich-labels="editorLabels"
    />
    <TranslatableInput
      v-model="form.epic_quote"
      :locales="locales.locales"
      :label="t('factions.fields.epicQuote')"
      type="wysiwyg"
      :icons="iconList"
      :rich-labels="editorLabels"
    />

    <PaletteColorPicker v-model="form.color" :label="t('factions.fields.color')" />

    <ImageUpload
      v-model="image"
      :current-url="currentImage"
      :label="t('factions.fields.image')"
      :drag-text="t('common.imageDrag')"
      :hint-text="t('common.imageHint')"
      :too-large-text="t('common.fileTooLarge')"
      :invalid-type-text="t('common.fileType')"
    />

    <BaseCheckbox v-model="form.is_published" :label="t('factions.fields.published')" />
  </EditModal>
</template>
