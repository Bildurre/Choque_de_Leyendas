<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BaseCheckbox, EditModal, NumericInput, TranslatableInput, useToast } from '@edc-motor/ui'
import { useResource } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useLocalesStore } from '@/stores/locales'
import type { GameMode } from '@juego/shared'

// Formulario de modo de juego en modal (por id, sin endpoint show: en
// edición se rellena desde el ítem del listado). Descripción en texto plano.
// Lleva integrada la configuración de mazos del modo (fusión de la antigua
// entidad aparte) y el flag "por defecto": marcar uno desmarca el anterior;
// el actual no se puede desmarcar (el checkbox queda bloqueado).
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  target?: GameMode | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const { create, update } = useResource<GameMode>(api, '/admin/game-modes')

const saving = ref(false)
const errors = reactive<Record<string, string>>({})

const form = reactive<{
  name: Record<string, string>
  description: Record<string, string>
  min_cards: number
  max_cards: number
  max_copies_per_card: number
  required_heroes: number
  is_default: boolean
}>({
  name: {},
  description: {},
  min_cards: 30,
  max_cards: 40,
  max_copies_per_card: 2,
  required_heroes: 0,
  is_default: false,
})

const title = computed(() => (props.mode === 'create' ? t('gameModes.new') : t('gameModes.edit')))

// El por defecto actual no puede dejar de serlo desde aquí: se desmarca solo
// al marcar otro modo (invariante "exactamente uno" del servidor).
const isCurrentDefault = computed(() => props.mode === 'edit' && !!props.target?.is_default)

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}
// Traduce cualquier error del backend con clave name.<locale> al campo 'name'.
function mapServerErrors(e: unknown) {
  for (const [k, v] of Object.entries(fieldErrors(e))) {
    if (k === 'name' || k.startsWith('name.')) errors.name = v
    if (k === 'description' || k.startsWith('description.')) errors.description = v
    if (
      ['min_cards', 'max_cards', 'max_copies_per_card', 'required_heroes', 'is_default'].includes(k)
    ) {
      errors[k] = v
    }
  }
}

// ¿Tiene el nombre algún valor en cualquier idioma?
const hasName = () => Object.values(form.name).some((v) => v && v.trim() !== '')

function reset() {
  form.name = {}
  form.description = {}
  form.min_cards = 30
  form.max_cards = 40
  form.max_copies_per_card = 2
  form.required_heroes = 0
  form.is_default = false
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
      form.description = { ...(props.target.description ?? {}) }
      form.min_cards = props.target.min_cards
      form.max_cards = props.target.max_cards
      form.max_copies_per_card = props.target.max_copies_per_card
      form.required_heroes = props.target.required_heroes
      form.is_default = !!props.target.is_default
    }
  },
)

async function submit() {
  clearErrors()
  if (!hasName()) {
    errors.name = t('common.required')
    return
  }
  // Validación mínima en cliente: evita un 422 innecesario y marca el campo.
  if (form.max_cards < form.min_cards) {
    errors.max_cards = t('gameModes.errors.maxBelowMin')
    return
  }
  saving.value = true
  const payload = {
    name: form.name,
    description: form.description,
    min_cards: form.min_cards,
    max_cards: form.max_cards,
    max_copies_per_card: form.max_copies_per_card,
    required_heroes: form.required_heroes,
    is_default: form.is_default,
  }
  try {
    if (props.mode === 'edit' && props.target) {
      await update(props.target.id, payload)
      toast.success(t('gameModes.toast.updated'))
    } else {
      await create(payload)
      toast.success(t('gameModes.toast.created'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    mapServerErrors(e)
    toast.danger(t('gameModes.toast.saveError'))
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
      :label="t('gameModes.fields.name')"
      required
      :error="errors.name"
    />

    <TranslatableInput
      v-model="form.description"
      :locales="locales.locales"
      :label="t('gameModes.fields.description')"
      type="textarea"
      :rows="4"
      :error="errors.description"
    />

    <!-- Configuración de mazos del modo (antes entidad aparte, fusionada) -->
    <h3 class="game-modes__form-section">{{ t('gameModes.sections.config') }}</h3>
    <div class="game-modes__config-grid">
      <NumericInput
        v-model="form.min_cards"
        :label="t('gameModes.fields.minCards')"
        :min="1"
        :max="200"
        :error="errors.min_cards"
      />
      <NumericInput
        v-model="form.max_cards"
        :label="t('gameModes.fields.maxCards')"
        :min="1"
        :max="200"
        :error="errors.max_cards"
      />
      <NumericInput
        v-model="form.max_copies_per_card"
        :label="t('gameModes.fields.maxCopiesPerCard')"
        :min="1"
        :max="20"
        :error="errors.max_copies_per_card"
      />
      <NumericInput
        v-model="form.required_heroes"
        :label="t('gameModes.fields.requiredHeroes')"
        :min="0"
        :max="20"
        :hint="t('gameModes.hints.requiredHeroes')"
        :error="errors.required_heroes"
      />
    </div>

    <BaseCheckbox
      v-model="form.is_default"
      :label="t('gameModes.fields.isDefault')"
      :disabled="isCurrentDefault"
    />
    <p class="game-modes__default-hint">{{ t('gameModes.hints.isDefault') }}</p>
    <p v-if="errors.is_default" class="game-modes__default-error">{{ errors.is_default }}</p>
  </EditModal>
</template>
