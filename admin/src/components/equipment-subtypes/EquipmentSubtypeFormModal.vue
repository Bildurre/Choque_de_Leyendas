<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BaseSelect, EditModal, TranslatableInput, useToast } from '@edc-motor/ui'
import { useResource } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useLocalesStore } from '@/stores/locales'
import type { EquipmentSubtype, EquipmentTypeOption, Translations } from '@juego/shared'

// Formulario de subtipo de equipo en modal (por id, sin endpoint show: en
// edición se rellena desde el ítem del listado). Todo subtipo pertenece a un
// tipo de equipo (obligatorio).
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  target?: EquipmentSubtype | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const { create, update } = useResource<EquipmentSubtype>(api, '/admin/equipment-subtypes')

const saving = ref(false)
const errors = reactive<Record<string, string>>({})
const equipmentTypes = ref<EquipmentTypeOption[]>([])

const form = reactive<{ name: Record<string, string>; equipment_type_id: string }>({
  name: {},
  equipment_type_id: '',
})

const title = computed(() =>
  props.mode === 'create' ? t('equipmentSubtypes.new') : t('equipmentSubtypes.edit'),
)

/** Etiqueta traducida de una opción en el locale activo. */
function optionLabel(option: { id: number; name: Translations }): string {
  return option.name?.[locales.current] || Object.values(option.name || {})[0] || `#${option.id}`
}

const typeOptions = computed(() =>
  equipmentTypes.value.map((o) => ({ value: String(o.id), label: optionLabel(o) })),
)

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}
// Traduce cualquier error del backend con clave name.<locale> al campo 'name'.
function mapServerErrors(e: unknown) {
  for (const [k, v] of Object.entries(fieldErrors(e))) {
    if (k === 'name' || k.startsWith('name.')) errors.name = v
    if (k === 'equipment_type_id') errors.equipment_type_id = v
  }
}

// ¿Tiene el nombre algún valor en cualquier idioma?
const hasName = () => Object.values(form.name).some((v) => v && v.trim() !== '')

function reset() {
  form.name = {}
  form.equipment_type_id = ''
  clearErrors()
}

// Al abrir: carga tipos de equipo; en edición copia el ítem del listado.
watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    reset()
    try {
      const [types] = await Promise.all([api.get('/admin/equipment-types/options'), locales.load()])
      equipmentTypes.value = types.data.data
    } catch {
      toast.danger(t('common.errors.load'))
    }
    if (props.mode === 'edit' && props.target) {
      form.name = { ...(props.target.name ?? {}) }
      form.equipment_type_id = props.target.equipment_type_id
        ? String(props.target.equipment_type_id)
        : ''
    }
  },
)

async function submit() {
  clearErrors()
  if (!hasName()) {
    errors.name = t('common.required')
  }
  if (!form.equipment_type_id) {
    errors.equipment_type_id = t('common.required')
  }
  if (errors.name || errors.equipment_type_id) return
  saving.value = true
  try {
    const payload = { name: form.name, equipment_type_id: form.equipment_type_id }
    if (props.mode === 'edit' && props.target) {
      await update(props.target.id, payload)
      toast.success(t('equipmentSubtypes.toast.updated'))
    } else {
      await create(payload)
      toast.success(t('equipmentSubtypes.toast.created'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    mapServerErrors(e)
    toast.danger(t('equipmentSubtypes.toast.saveError'))
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
      :label="t('equipmentSubtypes.fields.name')"
      required
      :error="errors.name"
    />

    <BaseSelect
      v-model="form.equipment_type_id"
      :options="typeOptions"
      :label="t('equipmentSubtypes.fields.type')"
      :placeholder="t('equipmentSubtypes.selectType')"
      required
      :error="errors.equipment_type_id"
    />
  </EditModal>
</template>
