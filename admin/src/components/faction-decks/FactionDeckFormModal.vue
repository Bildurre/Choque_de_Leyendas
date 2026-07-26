<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  BaseCheckbox,
  BaseSelect,
  EditModal,
  ImageUpload,
  MultiSelect,
  TranslatableInput,
  useToast,
} from '@edc-motor/ui'
import { useResource } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useEditorLabels } from '@/lib/editorLabels'
import { useLocalesStore } from '@/stores/locales'
import { useIconsStore } from '@/stores/icons'
import type { DeckPublishError, FactionDeck, FactionOption, Translations } from '@juego/shared'

// Datos básicos del mazo en modal (nombre, modo, facciones, textos, icono,
// publicado). Las cartas y los héroes se gestionan en la vista single.
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  targetSlug?: string | null
}>()

// `saved` lleva el mazo al CREAR: el listado salta directo a su editor
// (ahí viven los buscadores de cartas y héroes).
const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [created?: FactionDeck] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const icons = useIconsStore()
const { find, createForm, updateForm } = useResource<FactionDeck>(api, '/admin/faction-decks')
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
// Errores de publicación del servidor (claves i18n + parámetros)
const publishErrors = ref<DeckPublishError[]>([])

const gameModes = ref<{ id: number; name: Translations }[]>([])
const factions = ref<FactionOption[]>([])

const form = reactive<{
  name: Record<string, string>
  description: Record<string, string>
  epic_quote: Record<string, string>
  game_mode_id: number | ''
  faction_ids: string[]
  is_published: boolean
}>({
  name: {},
  description: {},
  epic_quote: {},
  game_mode_id: '',
  faction_ids: [],
  is_published: false,
})

const title = computed(() =>
  props.mode === 'create' ? t('factionDecks.new') : t('factionDecks.edit'),
)
// Iconos con URL para el selector del editor.
const iconList = computed(() =>
  icons.icons.filter((i) => i.url).map((i) => ({ name: i.name, url: i.url as string })),
)

function optionLabel(option: { id: number; name: Translations }): string {
  return option.name?.[locales.current] || Object.values(option.name || {})[0] || `#${option.id}`
}

// Modo de juego obligatorio: sin opción vacía (el placeholder es disabled).
const gameModeOptions = computed(() =>
  gameModes.value.map((o) => ({ value: o.id, label: optionLabel(o) })),
)

// Opciones del multiselect de facciones (varias por mazo).
const factionOptions = computed(() =>
  factions.value.map((f) => ({ value: String(f.id), label: optionLabel(f) })),
)

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
  publishErrors.value = []
}
function mapServerErrors(e: unknown) {
  for (const [k, v] of Object.entries(fieldErrors(e))) {
    if (k === 'name' || k.startsWith('name.')) errors.name = v
    if (k === 'description' || k.startsWith('description.')) errors.description = v
    if (k === 'epic_quote' || k.startsWith('epic_quote.')) errors.epic_quote = v
    if (['game_mode_id', 'faction_ids', 'image'].includes(k) || k.startsWith('faction_ids.')) {
      errors[k.startsWith('faction_ids.') ? 'faction_ids' : k] = v
    }
  }
  // Errores de publicación (límites del modo): lista aparte, localizable.
  const deck = (e as { response?: { data?: { errors?: { deck?: DeckPublishError[] } } } })?.response
    ?.data?.errors?.deck
  if (Array.isArray(deck)) publishErrors.value = deck
}

function reset() {
  form.name = {}
  form.description = {}
  form.epic_quote = {}
  form.game_mode_id = ''
  form.faction_ids = []
  form.is_published = false
  image.value = null
  currentImage.value = null
  removeImage.value = false
  clearErrors()
}

// ¿Tiene el nombre algún valor en cualquier idioma?
const hasName = () => Object.values(form.name).some((v) => v && v.trim() !== '')

// Al abrir: carga selectores y, en edición, el mazo por slug (show).
watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    reset()
    try {
      const [, , modes, facs] = await Promise.all([
        locales.load(),
        icons.load(),
        api.get('/admin/game-modes/options'),
        api.get('/admin/factions/options'),
      ])
      gameModes.value = modes.data.data
      factions.value = facs.data.data
    } catch {
      toast.danger(t('common.errors.load'))
    }
    if (props.mode === 'edit' && props.targetSlug) {
      try {
        const d = await find(props.targetSlug)
        form.name = d.name ?? {}
        form.description = d.description ?? {}
        form.epic_quote = d.epic_quote ?? {}
        form.game_mode_id = d.game_mode_id ?? ''
        form.faction_ids = (d.factions ?? []).map((f) => String(f.id))
        form.is_published = !!d.is_published
        currentImage.value = d.image ?? null
      } catch {
        toast.danger(t('factionDecks.toast.saveError'))
        emit('update:modelValue', false)
      }
    }
  },
)

function toFormData(): FormData {
  const fd = new FormData()
  for (const [k, v] of Object.entries(form.name)) fd.append(`name[${k}]`, v ?? '')
  for (const [k, v] of Object.entries(form.description)) fd.append(`description[${k}]`, v ?? '')
  for (const [k, v] of Object.entries(form.epic_quote)) fd.append(`epic_quote[${k}]`, v ?? '')
  fd.append('game_mode_id', form.game_mode_id === '' ? '' : String(form.game_mode_id))
  if (form.faction_ids.length) {
    for (const id of form.faction_ids) fd.append('faction_ids[]', String(id))
  } else {
    // Vacío explícito ('' → null en servidor): permite quitar todas
    fd.append('faction_ids', '')
  }
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
  if (form.game_mode_id === '') {
    errors.game_mode_id = t('common.required')
    return
  }
  if (!form.faction_ids.length) {
    errors.faction_ids = t('common.required')
    return
  }
  saving.value = true
  try {
    if (props.mode === 'edit' && props.targetSlug) {
      await updateForm(props.targetSlug, toFormData())
      toast.success(t('factionDecks.toast.updated'))
      emit('saved')
    } else {
      const created = await createForm(toFormData())
      toast.success(t('factionDecks.toast.created'))
      emit('saved', created)
    }
    emit('update:modelValue', false)
  } catch (e) {
    // Errores de validación por campo + aviso genérico. Nunca el volcado crudo.
    mapServerErrors(e)
    toast.danger(
      publishErrors.value.length
        ? t('factionDecks.toast.publishInvalid')
        : t('factionDecks.toast.saveError'),
    )
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
    <!-- Publicar exige cumplir los límites del modo: lista del servidor -->
    <ul v-if="publishErrors.length" class="faction-decks__publish-errors">
      <li v-for="(err, i) in publishErrors" :key="i">{{ t(err.key, err.params ?? {}) }}</li>
    </ul>

    <TranslatableInput
      v-model="form.name"
      :locales="locales.locales"
      :label="t('factionDecks.fields.name')"
      required
      :error="errors.name"
    />

    <BaseSelect
      v-model="form.game_mode_id"
      :label="t('factionDecks.fields.gameMode')"
      :options="gameModeOptions"
      :placeholder="t('factionDecks.fields.selectGameMode')"
      required
      :error="errors.game_mode_id"
    />

    <!-- Facciones del mazo: MULTISELECT (varias a la vez), como los filtros -->
    <MultiSelect
      v-model="form.faction_ids"
      :label="t('factionDecks.fields.factions')"
      :options="factionOptions"
      :placeholder="t('factionDecks.fields.selectFactions')"
      :error="errors.faction_ids"
    />

    <TranslatableInput
      v-model="form.description"
      :locales="locales.locales"
      :label="t('factionDecks.fields.description')"
      type="wysiwyg"
      :icons="iconList"
      :rich-labels="editorLabels"
      :error="errors.description"
    />
    <TranslatableInput
      v-model="form.epic_quote"
      :locales="locales.locales"
      :label="t('factionDecks.fields.epicQuote')"
      type="wysiwyg"
      :icons="iconList"
      :rich-labels="editorLabels"
      :error="errors.epic_quote"
    />

    <ImageUpload
      v-model="image"
      :current-url="currentImage"
      :label="t('factionDecks.fields.image')"
      :drag-text="t('common.imageDrag')"
      :hint-text="t('common.imageHint')"
      :too-large-text="t('common.fileTooLarge')"
      :invalid-type-text="t('common.fileType')"
      @remove="onRemoveImage"
    />

    <!-- Publicado solo en EDICIÓN: un mazo recién creado no puede cumplir
         los límites del modo (sus cartas y héroes se añaden después, en el
         editor del mazo) — ofrecer el check aquí era un callejón sin salida. -->
    <BaseCheckbox
      v-if="mode === 'edit'"
      v-model="form.is_published"
      :label="t('factionDecks.fields.published')"
    />
    <p v-else class="faction-decks__draft-hint">{{ t('factionDecks.fields.draftHint') }}</p>
  </EditModal>
</template>
