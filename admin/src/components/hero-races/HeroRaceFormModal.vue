<script setup lang="ts">
import { reactive, ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EditModal, TranslatableInput, useToast } from '@edc-motor/ui'
import { useResource } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useLocalesStore } from '@/stores/locales'
import type { HeroRace } from '@juego/shared'

// Formulario de raza en modal. Sin endpoint show: en edición se rellena
// desde el ítem ya cargado en el listado (prop target).
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  target?: HeroRace | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const { create, update } = useResource<HeroRace>(api, '/admin/hero-races')

const saving = ref(false)
const errors = reactive<Record<string, string>>({})

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}
// Traduce cualquier error del backend con clave name.<locale> al campo 'name'.
function mapServerErrors(e: unknown) {
  for (const [k, v] of Object.entries(fieldErrors(e))) {
    if (k === 'name_female' || k.startsWith('name_female.')) errors.name_female = v
    else if (k === 'name' || k.startsWith('name.')) errors.name = v
  }
}

const form = reactive<{ name: Record<string, string>; name_female: Record<string, string> }>({
  name: {},
  name_female: {},
})

const title = computed(() => (props.mode === 'create' ? t('heroRaces.new') : t('heroRaces.edit')))

// ¿Tiene el nombre algún valor en cualquier idioma?
const hasName = () => Object.values(form.name).some((v) => v && v.trim() !== '')

function reset() {
  form.name = {}
  form.name_female = {}
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
      form.name_female = { ...(props.target.name_female ?? {}) }
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
      await update(props.target.id, { name: form.name, name_female: form.name_female })
      toast.success(t('heroRaces.toast.updated'))
    } else {
      await create({ name: form.name, name_female: form.name_female })
      toast.success(t('heroRaces.toast.created'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    mapServerErrors(e)
    toast.danger(t('heroRaces.toast.saveError'))
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
      :label="t('heroRaces.fields.name')"
      required
      :error="errors.name"
    />
    <!-- Femenino opcional: se muestra solo junto a heroínas (HasGenderedName) -->
    <TranslatableInput
      v-model="form.name_female"
      :locales="locales.locales"
      :label="t('heroRaces.fields.nameFemale')"
      :error="errors.name_female"
    />
  </EditModal>
</template>
