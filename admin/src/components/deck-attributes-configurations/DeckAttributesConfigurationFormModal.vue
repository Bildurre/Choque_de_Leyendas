<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BaseSelect, EditModal, NumericInput, useToast } from '@edc-motor/ui'
import { useResource } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useLocalesStore } from '@/stores/locales'
import type { DeckAttributesConfig, TaxonomyOption, Translations } from '@juego/shared'

// Formulario de configuración de mazo en modal (por id; en edición se
// rellena desde el ítem del listado). Solo el modo y cuatro enteros.
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  target?: DeckAttributesConfig | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const { create, update } = useResource<DeckAttributesConfig>(
  api,
  '/admin/deck-attributes-configurations',
)

const saving = ref(false)
const errors = reactive<Record<string, string>>({})
const gameModes = ref<TaxonomyOption[]>([])

const form = reactive<{
  game_mode_id: number | ''
  min_cards: number
  max_cards: number
  max_copies_per_card: number
  required_heroes: number
}>({ game_mode_id: '', min_cards: 30, max_cards: 40, max_copies_per_card: 2, required_heroes: 0 })

const title = computed(() =>
  props.mode === 'create' ? t('deckAttributesConfigs.new') : t('deckAttributesConfigs.edit'),
)

function optionLabel(option: { id: number; name: Translations }): string {
  return option.name?.[locales.current] || Object.values(option.name || {})[0] || `#${option.id}`
}

// Opción vacía explícita: configuración genérica sin modo de juego.
const gameModeOptions = computed(() => [
  { value: '', label: t('deckAttributesConfigs.fields.noGameMode') },
  ...gameModes.value.map((o) => ({ value: o.id, label: optionLabel(o) })),
])

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}
function mapServerErrors(e: unknown) {
  for (const [k, v] of Object.entries(fieldErrors(e))) {
    if (k in form) errors[k] = v
  }
}

function reset() {
  form.game_mode_id = ''
  form.min_cards = 30
  form.max_cards = 40
  form.max_copies_per_card = 2
  form.required_heroes = 0
  clearErrors()
}

// Al abrir: carga los modos y, en edición, copia el ítem del listado.
watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    reset()
    try {
      const [, modes] = await Promise.all([locales.load(), api.get('/admin/game-modes/options')])
      gameModes.value = modes.data.data
    } catch {
      toast.danger(t('common.errors.load'))
    }
    if (props.mode === 'edit' && props.target) {
      form.game_mode_id = props.target.game_mode_id ?? ''
      form.min_cards = props.target.min_cards
      form.max_cards = props.target.max_cards
      form.max_copies_per_card = props.target.max_copies_per_card
      form.required_heroes = props.target.required_heroes
    }
  },
)

async function submit() {
  clearErrors()
  // Validación mínima en cliente: evita un 422 innecesario y marca el campo.
  if (form.max_cards < form.min_cards) {
    errors.max_cards = t('deckAttributesConfigs.errors.maxBelowMin')
    return
  }
  saving.value = true
  const payload = {
    game_mode_id: form.game_mode_id === '' ? null : form.game_mode_id,
    min_cards: form.min_cards,
    max_cards: form.max_cards,
    max_copies_per_card: form.max_copies_per_card,
    required_heroes: form.required_heroes,
  }
  try {
    if (props.mode === 'edit' && props.target) {
      await update(props.target.id, payload)
      toast.success(t('deckAttributesConfigs.toast.updated'))
    } else {
      await create(payload)
      toast.success(t('deckAttributesConfigs.toast.created'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    mapServerErrors(e)
    toast.danger(t('deckAttributesConfigs.toast.saveError'))
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
    <BaseSelect
      v-model="form.game_mode_id"
      :label="t('deckAttributesConfigs.fields.gameMode')"
      :options="gameModeOptions"
      :error="errors.game_mode_id"
    />

    <div class="deck-configs__grid">
      <NumericInput
        v-model="form.min_cards"
        :label="t('deckAttributesConfigs.fields.minCards')"
        :min="1"
        :max="200"
        :error="errors.min_cards"
      />
      <NumericInput
        v-model="form.max_cards"
        :label="t('deckAttributesConfigs.fields.maxCards')"
        :min="1"
        :max="200"
        :error="errors.max_cards"
      />
      <NumericInput
        v-model="form.max_copies_per_card"
        :label="t('deckAttributesConfigs.fields.maxCopiesPerCard')"
        :min="1"
        :max="20"
        :error="errors.max_copies_per_card"
      />
      <NumericInput
        v-model="form.required_heroes"
        :label="t('deckAttributesConfigs.fields.requiredHeroes')"
        :min="0"
        :max="20"
        :hint="t('deckAttributesConfigs.hints.requiredHeroes')"
        :error="errors.required_heroes"
      />
    </div>
  </EditModal>
</template>
