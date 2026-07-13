<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BaseCheckbox, EditModal, TranslatableInput, useToast } from '@edc-motor/ui'
import { useResource } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useLocalesStore } from '@/stores/locales'
import type { EquipmentType } from '@juego/shared'

// Formulario de tipo de equipo en modal (por id, sin endpoint show: en
// edición se rellena desde el ítem del listado). uses_hands marca los tipos
// que llevan manos (armas): sus cartas exigen el campo manos.
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  target?: EquipmentType | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const { create, update } = useResource<EquipmentType>(api, '/admin/equipment-types')

const saving = ref(false)
const errors = reactive<Record<string, string>>({})

const form = reactive<{ name: Record<string, string>; uses_hands: boolean }>({
  name: {},
  uses_hands: false,
})

const title = computed(() =>
  props.mode === 'create' ? t('equipmentTypes.new') : t('equipmentTypes.edit'),
)

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}
// Traduce cualquier error del backend con clave name.<locale> al campo 'name'.
function mapServerErrors(e: unknown) {
  for (const [k, v] of Object.entries(fieldErrors(e))) {
    if (k === 'name' || k.startsWith('name.')) errors.name = v
    if (k === 'uses_hands') errors.uses_hands = v
  }
}

// ¿Tiene el nombre algún valor en cualquier idioma?
const hasName = () => Object.values(form.name).some((v) => v && v.trim() !== '')

function reset() {
  form.name = {}
  form.uses_hands = false
  clearErrors()
}

// Al abrir: en edición copia el ítem del listado; en alta limpia.
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
    if (props.mode === 'edit' && props.target) {
      form.name = { ...(props.target.name ?? {}) }
      form.uses_hands = !!props.target.uses_hands
    }
  },
)

async function submit() {
  clearErrors()
  if (!hasName()) {
    errors.name = t('common.required')
    return
  }
  saving.value = true
  try {
    if (props.mode === 'edit' && props.target) {
      await update(props.target.id, { name: form.name, uses_hands: form.uses_hands })
      toast.success(t('equipmentTypes.toast.updated'))
    } else {
      await create({ name: form.name, uses_hands: form.uses_hands })
      toast.success(t('equipmentTypes.toast.created'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    mapServerErrors(e)
    toast.danger(t('equipmentTypes.toast.saveError'))
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
      :label="t('equipmentTypes.fields.name')"
      required
      :error="errors.name"
    />

    <BaseCheckbox v-model="form.uses_hands" :label="t('equipmentTypes.fields.usesHands')" />
  </EditModal>
</template>
