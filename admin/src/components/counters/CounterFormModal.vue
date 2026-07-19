<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  EditModal,
  TranslatableInput,
  ImageUpload,
  BaseSelect,
  BaseCheckbox,
  useToast,
} from '@edc-motor/ui'
import { useResource } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useEditorLabels } from '@/lib/editorLabels'
import { useLocalesStore } from '@/stores/locales'
import { useIconsStore } from '@/stores/icons'
import type { Counter } from '@juego/shared'

// Formulario de contador en modal (por id; en edición se rellena desde el
// ítem del listado). Efecto en wysiwyg con iconos del juego.
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  target?: Counter | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const icons = useIconsStore()
const { createForm, updateForm } = useResource<Counter>(api, '/admin/counters')
const editorLabels = useEditorLabels()

const saving = ref(false)
const image = ref<File | null>(null)
const currentImage = ref<string | null>(null)
// "Quitar imagen" DIFERIDO: solo marca el flag; el borrado real viaja con el
// GUARDAR (remove_image). Elegir un fichero nuevo lo desactiva.
const removeImage = ref(false)
watch(image, (file) => {
  if (file) removeImage.value = false
})
const errors = reactive<Record<string, string>>({})

function onRemoveImage() {
  removeImage.value = true
  currentImage.value = null
}

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}
// Traduce cualquier error del backend con clave campo.<locale> al campo raíz.
function mapServerErrors(e: unknown) {
  for (const [k, v] of Object.entries(fieldErrors(e))) {
    if (k === 'name' || k.startsWith('name.')) errors.name = v
    if (k === 'type') errors.type = v
  }
}

const form = reactive<{
  name: Record<string, string>
  effect: Record<string, string>
  type: string
  is_published: boolean
}>({ name: {}, effect: {}, type: '', is_published: false })

const title = computed(() => (props.mode === 'create' ? t('counters.new') : t('counters.edit')))
const typeOptions = computed(() => [
  { value: 'boon', label: t('counters.types.boon') },
  { value: 'bane', label: t('counters.types.bane') },
])
// Iconos del juego con URL para el selector del editor.
const iconList = computed(() =>
  icons.icons.filter((i) => i.url).map((i) => ({ name: i.name, url: i.url as string })),
)

function reset() {
  form.name = {}
  form.effect = {}
  form.type = ''
  form.is_published = false
  image.value = null
  currentImage.value = null
  removeImage.value = false
  clearErrors()
}

// ¿Tiene el nombre algún valor en cualquier idioma?
const hasName = () => Object.values(form.name).some((v) => v && v.trim() !== '')

// Al abrir: en edición copia el ítem del listado; en alta limpia.
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
    if (props.mode === 'edit' && props.target) {
      form.name = { ...(props.target.name ?? {}) }
      form.effect = { ...(props.target.effect ?? {}) }
      form.type = props.target.type ?? ''
      form.is_published = !!props.target.is_published
      currentImage.value = props.target.image ?? null
    }
  },
)

function toFormData(): FormData {
  const fd = new FormData()
  for (const [k, v] of Object.entries(form.name)) fd.append(`name[${k}]`, v ?? '')
  for (const [k, v] of Object.entries(form.effect)) fd.append(`effect[${k}]`, v ?? '')
  fd.append('type', form.type)
  fd.append('is_published', form.is_published ? '1' : '0')
  if (image.value) fd.append('image', image.value)
  else if (removeImage.value) fd.append('remove_image', '1')
  return fd
}

async function submit() {
  clearErrors()
  // Validación mínima en cliente: evita un 422 innecesario y marca el campo.
  if (!hasName()) {
    errors.name = t('common.required')
    return
  }
  if (!form.type) {
    errors.type = t('common.required')
    return
  }
  saving.value = true
  try {
    if (props.mode === 'edit' && props.target) {
      await updateForm(props.target.id, toFormData())
      toast.success(t('counters.toast.updated'))
    } else {
      await createForm(toFormData())
      toast.success(t('counters.toast.created'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    mapServerErrors(e)
    toast.danger(t('counters.toast.saveError'))
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
      :label="t('counters.fields.name')"
      required
      :error="errors.name"
    />

    <BaseSelect
      v-model="form.type"
      :label="t('counters.fields.type')"
      :options="typeOptions"
      :placeholder="t('counters.selectType')"
      required
      :error="errors.type"
    />

    <TranslatableInput
      v-model="form.effect"
      :locales="locales.locales"
      :label="t('counters.fields.effect')"
      type="wysiwyg"
      :icons="iconList"
      :rich-labels="editorLabels"
    />

    <ImageUpload
      v-model="image"
      :current-url="currentImage"
      :label="t('counters.fields.image')"
      :drag-text="t('common.imageDrag')"
      :hint-text="t('common.imageHint')"
      :too-large-text="t('common.fileTooLarge')"
      :invalid-type-text="t('common.fileType')"
      @remove="onRemoveImage"
    />

    <BaseCheckbox v-model="form.is_published" :label="t('counters.fields.published')" />
  </EditModal>
</template>
