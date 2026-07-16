<script setup lang="ts">
import { reactive, ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { BaseSelect, EditModal, TranslatableInput, useToast } from '@edc-motor/ui'
import { useResource } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useEditorLabels } from '@/lib/editorLabels'
import { useLocalesStore } from '@/stores/locales'
import { useIconsStore } from '@/stores/icons'
import { useHeroSuperclassesStore } from '@/stores/heroSuperclasses'
import type { HeroClass } from '@juego/shared'

// Formulario de clase de héroe en modal: nombre, pasiva (wysiwyg con los
// iconos del gestor) y superclase obligatoria. Sin endpoint show: en edición
// se rellena desde el ítem ya cargado en el listado (prop target).
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  target?: HeroClass | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const icons = useIconsStore()
const superclasses = useHeroSuperclassesStore()
const { create, update } = useResource<HeroClass>(api, '/admin/hero-classes')
const editorLabels = useEditorLabels()

const saving = ref(false)
const errors = reactive<Record<string, string>>({})

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}
function mapServerErrors(e: unknown) {
  for (const [k, v] of Object.entries(fieldErrors(e))) {
    if (k === 'name_female' || k.startsWith('name_female.')) errors.name_female = v
    else if (k === 'name' || k.startsWith('name.')) errors.name = v
    if (k === 'passive' || k.startsWith('passive.')) errors.passive = v
    if (k === 'hero_superclass_id') errors.hero_superclass_id = v
  }
}

const form = reactive<{
  name: Record<string, string>
  name_female: Record<string, string>
  passive: Record<string, string>
  hero_superclass_id: string
}>({ name: {}, name_female: {}, passive: {}, hero_superclass_id: '' })

const title = computed(() =>
  props.mode === 'create' ? t('heroClasses.new') : t('heroClasses.edit'),
)
// Iconos con URL para el editor de la pasiva (gestor de Iconos del motor).
const iconList = computed(() =>
  icons.icons.filter((i) => i.url).map((i) => ({ name: i.name, url: i.url as string })),
)
const superclassOptions = computed(() =>
  superclasses.options.map((s) => ({
    value: s.id,
    label: s.name?.[locales.current] || Object.values(s.name || {})[0] || `#${s.id}`,
  })),
)

// ¿Tiene el nombre algún valor en cualquier idioma?
const hasName = () => Object.values(form.name).some((v) => v && v.trim() !== '')

function reset() {
  form.name = {}
  form.name_female = {}
  form.passive = {}
  form.hero_superclass_id = ''
  clearErrors()
}

// Al abrir: en edición copia el ítem del listado; en alta limpia.
watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    reset()
    try {
      await Promise.all([locales.load(), icons.load(), superclasses.loadOptions()])
    } catch {
      toast.danger(t('common.errors.load'))
    }
    if (props.mode === 'edit' && props.target) {
      form.name = { ...(props.target.name ?? {}) }
      form.name_female = { ...(props.target.name_female ?? {}) }
      form.passive = { ...(props.target.passive ?? {}) }
      form.hero_superclass_id = props.target.hero_superclass_id
        ? String(props.target.hero_superclass_id)
        : ''
    }
  },
)

function payload() {
  return {
    name: form.name,
    name_female: form.name_female,
    passive: form.passive,
    hero_superclass_id: form.hero_superclass_id ? Number(form.hero_superclass_id) : null,
  }
}

async function submit() {
  clearErrors()
  // Validación mínima en cliente: evita un 422 innecesario y marca el campo.
  if (!hasName()) {
    errors.name = t('common.required')
    return
  }
  if (!form.hero_superclass_id) {
    errors.hero_superclass_id = t('common.required')
    return
  }
  saving.value = true
  try {
    if (props.mode === 'edit' && props.target) {
      await update(props.target.id, payload())
      toast.success(t('heroClasses.toast.updated'))
    } else {
      await create(payload())
      toast.success(t('heroClasses.toast.created'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    mapServerErrors(e)
    toast.danger(t('heroClasses.toast.saveError'))
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
      :label="t('heroClasses.fields.name')"
      required
      :error="errors.name"
    />
    <!-- Femenino opcional: se muestra solo junto a heroínas (HasGenderedName) -->
    <TranslatableInput
      v-model="form.name_female"
      :locales="locales.locales"
      :label="t('heroClasses.fields.nameFemale')"
      :error="errors.name_female"
    />
    <BaseSelect
      v-model="form.hero_superclass_id"
      :label="t('heroClasses.fields.superclass')"
      :options="superclassOptions"
      :placeholder="t('heroClasses.fields.selectSuperclass')"
      required
      :error="errors.hero_superclass_id"
    />
    <TranslatableInput
      v-model="form.passive"
      :locales="locales.locales"
      :label="t('heroClasses.fields.passive')"
      type="wysiwyg"
      :icons="iconList"
      :rich-labels="editorLabels"
      :error="errors.passive"
    />
  </EditModal>
</template>
