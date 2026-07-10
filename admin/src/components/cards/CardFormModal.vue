<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  BaseCheckbox,
  BaseSelect,
  EditModal,
  ImageUpload,
  TranslatableInput,
  useToast,
} from '@edc-motor/ui'
import { useResource } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useEditorLabels } from '@/lib/editorLabels'
import { useLocalesStore } from '@/stores/locales'
import { useIconsStore } from '@/stores/icons'
import CostInput from '@/components/game/CostInput.vue'
import type {
  Card,
  CardTypeOption,
  EquipmentTypeOption,
  FactionOption,
  HeroAbilityOption,
  TaxonomyOption,
  Translations,
} from '@juego/shared'

// Formulario de carta en modal, con los fieldsets del form del viejo pero
// reactivo a los flags del tipo elegido: allows_subtypes muestra el subtipo
// e is_equipment el tipo de equipo + manos. En edición carga por slug (show).
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  targetSlug?: string | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const icons = useIconsStore()
const { find, createForm, updateForm } = useResource<Card>(api, '/admin/cards')
const editorLabels = useEditorLabels()

const saving = ref(false)
const image = ref<File | null>(null)
const currentImage = ref<string | null>(null)
const errors = reactive<Record<string, string>>({})

const factions = ref<FactionOption[]>([])
const cardTypes = ref<CardTypeOption[]>([])
const cardSubtypes = ref<TaxonomyOption[]>([])
const equipmentTypes = ref<EquipmentTypeOption[]>([])
const attackRanges = ref<TaxonomyOption[]>([])
const attackSubtypes = ref<TaxonomyOption[]>([])
const heroAbilities = ref<HeroAbilityOption[]>([])

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}
function mapServerErrors(e: unknown) {
  for (const [k, v] of Object.entries(fieldErrors(e))) {
    if (k === 'name' || k.startsWith('name.')) errors.name = v
    if (k === 'effect' || k.startsWith('effect.')) errors.effect = v
    if (k === 'restriction' || k.startsWith('restriction.')) errors.restriction = v
    if (k === 'lore_text' || k.startsWith('lore_text.')) errors.lore_text = v
    if (k === 'epic_quote' || k.startsWith('epic_quote.')) errors.epic_quote = v
    if (
      [
        'faction_id',
        'card_type_id',
        'card_subtype_id',
        'equipment_type_id',
        'attack_type',
        'attack_range_id',
        'attack_subtype_id',
        'hero_ability_id',
        'hands',
        'cost',
        'image',
      ].includes(k)
    ) {
      errors[k] = v
    }
  }
}

const form = reactive<{
  name: Record<string, string>
  lore_text: Record<string, string>
  epic_quote: Record<string, string>
  effect: Record<string, string>
  restriction: Record<string, string>
  faction_id: string
  card_type_id: string
  card_subtype_id: string
  equipment_type_id: string
  attack_type: string
  attack_range_id: string
  attack_subtype_id: string
  hero_ability_id: string
  hands: string
  cost: string
  area: boolean
  is_unique: boolean
  is_published: boolean
}>({
  name: {},
  lore_text: {},
  epic_quote: {},
  effect: {},
  restriction: {},
  faction_id: '',
  card_type_id: '',
  card_subtype_id: '',
  equipment_type_id: '',
  attack_type: '',
  attack_range_id: '',
  attack_subtype_id: '',
  hero_ability_id: '',
  hands: '',
  cost: '',
  area: false,
  is_unique: false,
  is_published: false,
})

const title = computed(() => (props.mode === 'create' ? t('cards.new') : t('cards.edit')))
// Iconos con URL para los editores wysiwyg (gestor de Iconos del motor).
const iconList = computed(() =>
  icons.icons.filter((i) => i.url).map((i) => ({ name: i.name, url: i.url as string })),
)

/** Etiqueta traducida de una opción en el locale activo. */
function optionLabel(option: { id: number; name: Translations }): string {
  return option.name?.[locales.current] || Object.values(option.name || {})[0] || `#${option.id}`
}

// --- Flags del tipo elegido: condicionan subtipo y equipo/manos ---
const selectedType = computed(
  () => cardTypes.value.find((type) => String(type.id) === form.card_type_id) ?? null,
)
const allowsSubtypes = computed(() => !!selectedType.value?.allows_subtypes)
const isEquipment = computed(() => !!selectedType.value?.is_equipment)
const selectedEquipment = computed(
  () => equipmentTypes.value.find((e) => String(e.id) === form.equipment_type_id) ?? null,
)
// Manos obligatorias solo para armas (regla del viejo; el server la valida).
const handsRequired = computed(() => selectedEquipment.value?.category === 'weapon')

// Al cambiar el tipo se limpia lo que sus flags ya no permiten.
watch(selectedType, (type) => {
  if (!type?.allows_subtypes) form.card_subtype_id = ''
  if (!type?.is_equipment) {
    form.equipment_type_id = ''
    form.hands = ''
  }
})

// Opción vacía explícita: permite volver a "sin valor" (placeholder es disabled).
const factionOptions = computed(() => [
  { value: '', label: t('cards.fields.noFaction') },
  ...factions.value.map((o) => ({ value: o.id, label: optionLabel(o) })),
])
const cardTypeOptions = computed(() =>
  cardTypes.value.map((o) => ({ value: o.id, label: optionLabel(o) })),
)
const cardSubtypeOptions = computed(() => [
  { value: '', label: t('cards.fields.noSubtype') },
  ...cardSubtypes.value.map((o) => ({ value: o.id, label: optionLabel(o) })),
])
const equipmentTypeOptions = computed(() => [
  { value: '', label: t('cards.fields.noEquipmentType') },
  ...equipmentTypes.value.map((o) => ({ value: o.id, label: optionLabel(o) })),
])
const handsOptions = computed(() => [
  { value: '', label: t('cards.fields.selectHands') },
  { value: '1', label: t('cards.fields.oneHand') },
  { value: '2', label: t('cards.fields.twoHands') },
])
const heroAbilityOptions = computed(() => [
  { value: '', label: t('cards.fields.noHeroAbility') },
  ...heroAbilities.value.map((o) => ({
    value: o.id,
    label: o.cost ? `${optionLabel(o)} (${o.cost})` : optionLabel(o),
  })),
])
const attackRangeOptions = computed(() => [
  { value: '', label: t('cards.fields.noAttackRange') },
  ...attackRanges.value.map((o) => ({ value: o.id, label: optionLabel(o) })),
])
const attackTypeOptions = computed(() => [
  { value: '', label: t('cards.fields.noAttackType') },
  { value: 'physical', label: t('cards.attackTypes.physical') },
  { value: 'magical', label: t('cards.attackTypes.magical') },
])
const attackSubtypeOptions = computed(() => [
  { value: '', label: t('cards.fields.noAttackSubtype') },
  ...attackSubtypes.value.map((o) => ({ value: o.id, label: optionLabel(o) })),
])

// ¿Tiene el nombre algún valor en cualquier idioma?
const hasName = () => Object.values(form.name).some((v) => v && v.trim() !== '')

function reset() {
  form.name = {}
  form.lore_text = {}
  form.epic_quote = {}
  form.effect = {}
  form.restriction = {}
  form.faction_id = ''
  form.card_type_id = ''
  form.card_subtype_id = ''
  form.equipment_type_id = ''
  form.attack_type = ''
  form.attack_range_id = ''
  form.attack_subtype_id = ''
  form.hero_ability_id = ''
  form.hands = ''
  form.cost = ''
  form.area = false
  form.is_unique = false
  form.is_published = false
  image.value = null
  currentImage.value = null
  clearErrors()
}

// Al abrir: carga selectores (endpoints options de otros clusters); en
// edición, la carta por slug.
watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    reset()
    try {
      const [f, ct, cs, et, ar, as, ha] = await Promise.all([
        api.get('/admin/factions/options'),
        api.get('/admin/card-types/options'),
        api.get('/admin/card-subtypes/options'),
        api.get('/admin/equipment-types/options'),
        api.get('/admin/attack-ranges/options'),
        api.get('/admin/attack-subtypes/options'),
        api.get('/admin/hero-abilities/options'),
        locales.load(),
        icons.load(),
      ])
      factions.value = f.data.data
      cardTypes.value = ct.data.data
      cardSubtypes.value = cs.data.data
      equipmentTypes.value = et.data.data
      attackRanges.value = ar.data.data
      attackSubtypes.value = as.data.data
      heroAbilities.value = ha.data.data
    } catch {
      toast.danger(t('common.errors.load'))
    }
    if (props.mode === 'edit' && props.targetSlug) {
      try {
        const card = await find(props.targetSlug)
        form.name = card.name ?? {}
        form.lore_text = card.lore_text ?? {}
        form.epic_quote = card.epic_quote ?? {}
        form.effect = card.effect ?? {}
        form.restriction = card.restriction ?? {}
        form.faction_id = card.faction_id ? String(card.faction_id) : ''
        form.card_type_id = card.card_type_id ? String(card.card_type_id) : ''
        form.card_subtype_id = card.card_subtype_id ? String(card.card_subtype_id) : ''
        form.equipment_type_id = card.equipment_type_id ? String(card.equipment_type_id) : ''
        form.attack_type = card.attack_type ?? ''
        form.attack_range_id = card.attack_range_id ? String(card.attack_range_id) : ''
        form.attack_subtype_id = card.attack_subtype_id ? String(card.attack_subtype_id) : ''
        form.hero_ability_id = card.hero_ability_id ? String(card.hero_ability_id) : ''
        form.hands = card.hands ? String(card.hands) : ''
        form.cost = card.cost ?? ''
        form.area = !!card.area
        form.is_unique = !!card.is_unique
        form.is_published = !!card.is_published
        currentImage.value = card.image ?? null
      } catch {
        toast.danger(t('cards.toast.saveError'))
        emit('update:modelValue', false)
      }
    }
  },
)

function toFormData(): FormData {
  const fd = new FormData()
  const translatables: [string, Record<string, string>][] = [
    ['name', form.name],
    ['lore_text', form.lore_text],
    ['epic_quote', form.epic_quote],
    ['effect', form.effect],
    ['restriction', form.restriction],
  ]
  for (const [field, values] of translatables) {
    for (const [locale, value] of Object.entries(values))
      fd.append(`${field}[${locale}]`, value ?? '')
  }
  fd.append('faction_id', form.faction_id)
  fd.append('card_type_id', form.card_type_id)
  // Los campos que los flags ocultan viajan vacíos (el server los anula igual)
  fd.append('card_subtype_id', allowsSubtypes.value ? form.card_subtype_id : '')
  fd.append('equipment_type_id', isEquipment.value ? form.equipment_type_id : '')
  fd.append('hands', isEquipment.value ? form.hands : '')
  fd.append('attack_type', form.attack_type)
  fd.append('attack_range_id', form.attack_range_id)
  fd.append('attack_subtype_id', form.attack_subtype_id)
  fd.append('hero_ability_id', form.hero_ability_id)
  fd.append('cost', form.cost)
  fd.append('area', form.area ? '1' : '0')
  fd.append('is_unique', form.is_unique ? '1' : '0')
  fd.append('is_published', form.is_published ? '1' : '0')
  if (image.value) fd.append('image', image.value)
  return fd
}

async function submit() {
  clearErrors()
  // Validación mínima en cliente: evita un 422 innecesario y marca el campo.
  if (!hasName()) {
    errors.name = t('common.required')
    return
  }
  if (!form.card_type_id) {
    errors.card_type_id = t('common.required')
    return
  }
  saving.value = true
  try {
    if (props.mode === 'edit' && props.targetSlug) {
      await updateForm(props.targetSlug, toFormData())
      toast.success(t('cards.toast.updated'))
    } else {
      await createForm(toFormData())
      toast.success(t('cards.toast.created'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    mapServerErrors(e)
    toast.danger(t('cards.toast.saveError'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <EditModal
    :model-value="modelValue"
    :title="title"
    size="lg"
    :loading="saving"
    :submit-label="t('common.save')"
    :cancel-label="t('common.cancel')"
    @update:model-value="(v: boolean) => emit('update:modelValue', v)"
    @submit="submit"
  >
    <!-- Básicos -->
    <fieldset class="card-form__fieldset">
      <legend>{{ t('cards.sections.basic') }}</legend>
      <TranslatableInput
        v-model="form.name"
        :locales="locales.locales"
        :label="t('cards.fields.name')"
        required
        :error="errors.name"
      />
      <div class="card-form__grid">
        <BaseSelect
          v-model="form.faction_id"
          :label="t('cards.fields.faction')"
          :options="factionOptions"
          :error="errors.faction_id"
        />
        <CostInput
          v-model="form.cost"
          :label="t('cards.fields.cost')"
          :remove-label="t('cards.fields.removeDie')"
          :error="errors.cost"
        />
      </div>
      <div class="card-form__checks">
        <BaseCheckbox v-model="form.is_published" :label="t('cards.fields.published')" />
        <BaseCheckbox v-model="form.is_unique" :label="t('cards.fields.isUnique')" />
      </div>

      <div class="card-form__grid">
        <BaseSelect
          v-model="form.card_type_id"
          :label="t('cards.fields.type')"
          :options="cardTypeOptions"
          :placeholder="t('cards.fields.selectType')"
          required
          :error="errors.card_type_id"
        />
        <!-- Subtipo: solo si el tipo elegido admite subtipos -->
        <BaseSelect
          v-if="allowsSubtypes"
          v-model="form.card_subtype_id"
          :label="t('cards.fields.subtype')"
          :options="cardSubtypeOptions"
          :error="errors.card_subtype_id"
        />
        <!-- Equipo + manos: solo si el tipo elegido es equipamiento -->
        <template v-if="isEquipment">
          <BaseSelect
            v-model="form.equipment_type_id"
            :label="t('cards.fields.equipmentType')"
            :options="equipmentTypeOptions"
            :error="errors.equipment_type_id"
          />
          <BaseSelect
            v-model="form.hands"
            :label="t('cards.fields.hands')"
            :options="handsOptions"
            :required="handsRequired"
            :error="errors.hands"
          />
        </template>
        <BaseSelect
          v-model="form.hero_ability_id"
          :label="t('cards.fields.heroAbility')"
          :options="heroAbilityOptions"
          :error="errors.hero_ability_id"
        />
      </div>

      <div class="card-form__grid">
        <BaseSelect
          v-model="form.attack_range_id"
          :label="t('cards.fields.attackRange')"
          :options="attackRangeOptions"
          :error="errors.attack_range_id"
        />
        <BaseSelect
          v-model="form.attack_type"
          :label="t('cards.fields.attackType')"
          :options="attackTypeOptions"
          :error="errors.attack_type"
        />
        <BaseSelect
          v-model="form.attack_subtype_id"
          :label="t('cards.fields.attackSubtype')"
          :options="attackSubtypeOptions"
          :error="errors.attack_subtype_id"
        />
      </div>
      <BaseCheckbox v-model="form.area" :label="t('cards.fields.area')" />

      <ImageUpload
        v-model="image"
        :current-url="currentImage"
        :label="t('cards.fields.image')"
        :drag-text="t('common.imageDrag')"
        :hint-text="t('common.imageHint')"
        :too-large-text="t('common.fileTooLarge')"
        :invalid-type-text="t('common.fileType')"
      />
    </fieldset>

    <!-- Efectos -->
    <fieldset class="card-form__fieldset">
      <legend>{{ t('cards.sections.effects') }}</legend>
      <TranslatableInput
        v-model="form.effect"
        :locales="locales.locales"
        :label="t('cards.fields.effect')"
        type="wysiwyg"
        :icons="iconList"
        :rich-labels="editorLabels"
        :error="errors.effect"
      />
      <TranslatableInput
        v-model="form.restriction"
        :locales="locales.locales"
        :label="t('cards.fields.restriction')"
        type="wysiwyg"
        :icons="iconList"
        :rich-labels="editorLabels"
        :error="errors.restriction"
      />
    </fieldset>

    <!-- Trasfondo -->
    <fieldset class="card-form__fieldset">
      <legend>{{ t('cards.sections.lore') }}</legend>
      <TranslatableInput
        v-model="form.lore_text"
        :locales="locales.locales"
        :label="t('cards.fields.loreText')"
        type="wysiwyg"
        :icons="iconList"
        :rich-labels="editorLabels"
        :error="errors.lore_text"
      />
      <TranslatableInput
        v-model="form.epic_quote"
        :locales="locales.locales"
        :label="t('cards.fields.epicQuote')"
        type="wysiwyg"
        :icons="iconList"
        :rich-labels="editorLabels"
        :error="errors.epic_quote"
      />
    </fieldset>
  </EditModal>
</template>
