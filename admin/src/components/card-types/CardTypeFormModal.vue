<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EditModal, TranslatableInput, BaseSelect, BaseCheckbox, useToast } from '@edc-motor/ui'
import { useResource } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useLocalesStore } from '@/stores/locales'
import type { CardType, TaxonomyOption, Translations } from '@juego/shared'

// Formulario de tipo de carta en modal (por id, sin endpoint show: en edición
// se rellena desde el ítem del listado). Los dos checkboxes sustituyen a los
// ids mágicos del viejo (technique/spell/litany): allows_subtypes habilita el
// select de subtipo en cartas; is_equipment, el tipo de equipo y las manos.
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  target?: CardType | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const { create, update } = useResource<CardType>(api, '/admin/card-types')

const saving = ref(false)
const errors = reactive<Record<string, string>>({})
const superclasses = ref<TaxonomyOption[]>([])

const form = reactive<{
  name: Record<string, string>
  hero_superclass_id: string
  allows_subtypes: boolean
  is_equipment: boolean
}>({ name: {}, hero_superclass_id: '', allows_subtypes: false, is_equipment: false })

const title = computed(() => (props.mode === 'create' ? t('cardTypes.new') : t('cardTypes.edit')))

/** Nombre traducido de una opción (locale activo con fallback). */
function optionLabel(name: Translations): string {
  return name[locales.current] || name[locales.defaultLocale] || Object.values(name)[0] || ''
}

// El BaseSelect siempre entrega string; '' = sin superclase.
const superclassOptions = computed(() => [
  { value: '', label: t('cardTypes.noSuperclass') },
  ...superclasses.value.map((s) => ({ value: String(s.id), label: optionLabel(s.name) })),
])

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}
// Traduce cualquier error del backend con clave name.<locale> al campo 'name'.
function mapServerErrors(e: unknown) {
  for (const [k, v] of Object.entries(fieldErrors(e))) {
    if (k === 'name' || k.startsWith('name.')) errors.name = v
    if (k === 'hero_superclass_id') errors.hero_superclass_id = v
  }
}

// ¿Tiene el nombre algún valor en cualquier idioma?
const hasName = () => Object.values(form.name).some((v) => v && v.trim() !== '')

function reset() {
  form.name = {}
  form.hero_superclass_id = ''
  form.allows_subtypes = false
  form.is_equipment = false
  clearErrors()
}

// Al abrir: carga locales + superclases; en edición copia el ítem del listado.
watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    reset()
    try {
      await locales.load()
    } catch {
      toast.danger(t('common.errors.load'))
    }
    try {
      const { data } = await api.get('/admin/hero-superclasses/options')
      superclasses.value = data.data ?? []
    } catch {
      superclasses.value = [] // sin opciones: el select solo ofrece "sin superclase"
    }
    if (props.mode === 'edit' && props.target) {
      form.name = { ...(props.target.name ?? {}) }
      form.hero_superclass_id = props.target.hero_superclass_id
        ? String(props.target.hero_superclass_id)
        : ''
      form.allows_subtypes = !!props.target.allows_subtypes
      form.is_equipment = !!props.target.is_equipment
    }
  },
)

function payload() {
  return {
    name: form.name,
    hero_superclass_id: form.hero_superclass_id ? Number(form.hero_superclass_id) : null,
    allows_subtypes: form.allows_subtypes,
    is_equipment: form.is_equipment,
  }
}

async function submit() {
  clearErrors()
  if (!hasName()) {
    errors.name = t('common.required')
    return
  }
  saving.value = true
  try {
    if (props.mode === 'edit' && props.target) {
      await update(props.target.id, payload())
      toast.success(t('cardTypes.toast.updated'))
    } else {
      await create(payload())
      toast.success(t('cardTypes.toast.created'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    mapServerErrors(e)
    toast.danger(t('cardTypes.toast.saveError'))
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
      :label="t('cardTypes.fields.name')"
      required
      :error="errors.name"
    />

    <BaseSelect
      v-model="form.hero_superclass_id"
      :options="superclassOptions"
      :label="t('cardTypes.fields.heroSuperclass')"
      :error="errors.hero_superclass_id"
    />

    <BaseCheckbox v-model="form.allows_subtypes" :label="t('cardTypes.fields.allowsSubtypes')" />
    <BaseCheckbox v-model="form.is_equipment" :label="t('cardTypes.fields.isEquipment')" />
  </EditModal>
</template>
