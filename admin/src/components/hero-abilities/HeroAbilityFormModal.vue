<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BaseCheckbox, BaseSelect, EditModal, TranslatableInput, useToast } from '@edc-motor/ui'
import { useResource } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useEditorLabels } from '@/lib/editorLabels'
import { useLocalesStore } from '@/stores/locales'
import { useIconsStore } from '@/stores/icons'
import CostInput from '@/components/game/CostInput.vue'
import type { HeroAbility, TaxonomyOption } from '@juego/shared'

// Formulario de habilidad activa en modal: nombre, coste en dados (CostInput),
// datos de ataque opcionales y descripción wysiwyg. Sin endpoint show: en
// edición se rellena desde el ítem ya cargado en el listado (prop target).
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  target?: HeroAbility | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const icons = useIconsStore()
const { create, update } = useResource<HeroAbility>(api, '/admin/hero-abilities')
const editorLabels = useEditorLabels()

const saving = ref(false)
const errors = reactive<Record<string, string>>({})

const attackRanges = ref<TaxonomyOption[]>([])
const attackSubtypes = ref<TaxonomyOption[]>([])

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}
function mapServerErrors(e: unknown) {
  for (const [k, v] of Object.entries(fieldErrors(e))) {
    if (k === 'name' || k.startsWith('name.')) errors.name = v
    if (k === 'description' || k.startsWith('description.')) errors.description = v
    if (['cost', 'attack_type', 'attack_range_id', 'attack_subtype_id'].includes(k)) errors[k] = v
  }
}

const form = reactive<{
  name: Record<string, string>
  description: Record<string, string>
  attack_type: string
  attack_range_id: string
  attack_subtype_id: string
  area: boolean
  cost: string
}>({
  name: {},
  description: {},
  attack_type: '',
  attack_range_id: '',
  attack_subtype_id: '',
  area: false,
  cost: '',
})

const title = computed(() =>
  props.mode === 'create' ? t('heroAbilities.new') : t('heroAbilities.edit'),
)
// Iconos con URL para el editor de la descripción (gestor de Iconos del motor).
const iconList = computed(() =>
  icons.icons.filter((i) => i.url).map((i) => ({ name: i.name, url: i.url as string })),
)

/** Etiqueta traducida de una opción de taxonomía en el locale activo. */
function optionLabel(option: TaxonomyOption): string {
  return option.name?.[locales.current] || Object.values(option.name || {})[0] || `#${option.id}`
}

// Opción vacía explícita: permite volver a "sin valor" (placeholder es disabled).
const attackTypeOptions = computed(() => [
  { value: '', label: t('heroAbilities.fields.noAttackType') },
  { value: 'physical', label: t('heroAbilities.attackTypes.physical') },
  { value: 'magical', label: t('heroAbilities.attackTypes.magical') },
])
const attackRangeOptions = computed(() => [
  { value: '', label: t('heroAbilities.fields.noAttackRange') },
  ...attackRanges.value.map((o) => ({ value: o.id, label: optionLabel(o) })),
])
const attackSubtypeOptions = computed(() => [
  { value: '', label: t('heroAbilities.fields.noAttackSubtype') },
  ...attackSubtypes.value.map((o) => ({ value: o.id, label: optionLabel(o) })),
])

// ¿Tiene el nombre algún valor en cualquier idioma?
const hasName = () => Object.values(form.name).some((v) => v && v.trim() !== '')

function reset() {
  form.name = {}
  form.description = {}
  form.attack_type = ''
  form.attack_range_id = ''
  form.attack_subtype_id = ''
  form.area = false
  form.cost = ''
  clearErrors()
}

// Al abrir: carga selectores; en edición copia el ítem del listado.
watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    reset()
    try {
      const [ranges, subtypes] = await Promise.all([
        api.get('/admin/attack-ranges/options'),
        api.get('/admin/attack-subtypes/options'),
        locales.load(),
        icons.load(),
      ])
      attackRanges.value = ranges.data.data
      attackSubtypes.value = subtypes.data.data
    } catch {
      toast.danger(t('common.errors.load'))
    }
    if (props.mode === 'edit' && props.target) {
      form.name = { ...(props.target.name ?? {}) }
      form.description = { ...(props.target.description ?? {}) }
      form.attack_type = props.target.attack_type ?? ''
      form.attack_range_id = props.target.attack_range_id
        ? String(props.target.attack_range_id)
        : ''
      form.attack_subtype_id = props.target.attack_subtype_id
        ? String(props.target.attack_subtype_id)
        : ''
      form.area = !!props.target.area
      form.cost = props.target.cost ?? ''
    }
  },
)

function payload() {
  return {
    name: form.name,
    description: form.description,
    attack_type: form.attack_type || null,
    attack_range_id: form.attack_range_id ? Number(form.attack_range_id) : null,
    attack_subtype_id: form.attack_subtype_id ? Number(form.attack_subtype_id) : null,
    area: form.area,
    cost: form.cost,
  }
}

async function submit() {
  clearErrors()
  // Validación mínima en cliente: evita un 422 innecesario y marca el campo.
  if (!hasName()) {
    errors.name = t('common.required')
    return
  }
  if (!form.cost) {
    errors.cost = t('common.required')
    return
  }
  saving.value = true
  try {
    if (props.mode === 'edit' && props.target) {
      await update(props.target.id, payload())
      toast.success(t('heroAbilities.toast.updated'))
    } else {
      await create(payload())
      toast.success(t('heroAbilities.toast.created'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    mapServerErrors(e)
    toast.danger(t('heroAbilities.toast.saveError'))
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
      :label="t('heroAbilities.fields.name')"
      required
      :error="errors.name"
    />

    <CostInput
      v-model="form.cost"
      :label="t('heroAbilities.fields.cost')"
      :max="5"
      :error="errors.cost"
      :remove-label="t('heroAbilities.fields.removeDie')"
    />

    <BaseSelect
      v-model="form.attack_type"
      :label="t('heroAbilities.fields.attackType')"
      :options="attackTypeOptions"
      :error="errors.attack_type"
    />
    <BaseSelect
      v-model="form.attack_range_id"
      :label="t('heroAbilities.fields.attackRange')"
      :options="attackRangeOptions"
      :error="errors.attack_range_id"
    />
    <BaseSelect
      v-model="form.attack_subtype_id"
      :label="t('heroAbilities.fields.attackSubtype')"
      :options="attackSubtypeOptions"
      :error="errors.attack_subtype_id"
    />

    <BaseCheckbox v-model="form.area" :label="t('heroAbilities.fields.area')" />

    <TranslatableInput
      v-model="form.description"
      :locales="locales.locales"
      :label="t('heroAbilities.fields.description')"
      type="wysiwyg"
      :icons="iconList"
      :rich-labels="editorLabels"
      :error="errors.description"
    />
  </EditModal>
</template>
