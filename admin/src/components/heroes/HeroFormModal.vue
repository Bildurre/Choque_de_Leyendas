<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ArrowDown, ArrowUp, X } from '@lucide/vue'
import {
  BaseCheckbox,
  BaseSelect,
  EditModal,
  ImageUpload,
  NumericInput,
  TranslatableInput,
  useToast,
} from '@edc-motor/ui'
import { useResource } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useEditorLabels } from '@/lib/editorLabels'
import { useLocalesStore } from '@/stores/locales'
import { useIconsStore } from '@/stores/icons'
import SearchCombobox from '@/components/SearchCombobox.vue'
import CostDice from '@/components/game/CostDice.vue'
import type {
  Hero,
  HeroAbilityOption,
  HeroAttributesConfig,
  TaxonomyOption,
  Translations,
} from '@juego/shared'

// Formulario de héroe en modal, con los fieldsets del form del viejo:
// básicos, atributos (límites de la configuración), pasiva, habilidades
// activas ordenables y trasfondo. En edición carga el héroe por slug (show).
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
const { find, createForm, updateForm } = useResource<Hero>(api, '/admin/heroes')
const editorLabels = useEditorLabels()

const saving = ref(false)
const image = ref<File | null>(null)
const currentImage = ref<string | null>(null)
const errors = reactive<Record<string, string>>({})

const factions = ref<TaxonomyOption[]>([])
const races = ref<TaxonomyOption[]>([])
const classes = ref<TaxonomyOption[]>([])
const abilityOptions = ref<AbilityOption[]>([])
const config = ref<HeroAttributesConfig | null>(null)

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}
function mapServerErrors(e: unknown) {
  for (const [k, v] of Object.entries(fieldErrors(e))) {
    if (k === 'name' || k.startsWith('name.')) errors.name = v
    if (k === 'passive_name' || k.startsWith('passive_name.')) errors.passive_name = v
    if (k === 'passive_description' || k.startsWith('passive_description.'))
      errors.passive_description = v
    if (k === 'lore_text' || k.startsWith('lore_text.')) errors.lore_text = v
    if (k === 'epic_quote' || k.startsWith('epic_quote.')) errors.epic_quote = v
    if (k === 'abilities' || k.startsWith('abilities.')) errors.abilities = v
    if (
      ['faction_id', 'hero_race_id', 'hero_class_id', 'gender', 'image'].includes(k) ||
      ['agility', 'mental', 'will', 'strength', 'armor'].includes(k)
    ) {
      errors[k] = v
    }
  }
}

/** Forma del endpoint /admin/hero-abilities/options (range/subtype anulables). */
interface AbilityOption extends HeroAbilityOption {
  attack_type?: 'physical' | 'magical' | null
  range?: TaxonomyOption | null
  subtype?: TaxonomyOption | null
}

interface SelectedAbility {
  id: number
  name: Translations
  cost: string
  attack_type?: 'physical' | 'magical' | null
  range?: TaxonomyOption | null
  subtype?: TaxonomyOption | null
}

const form = reactive<{
  name: Record<string, string>
  lore_text: Record<string, string>
  epic_quote: Record<string, string>
  passive_name: Record<string, string>
  passive_description: Record<string, string>
  faction_id: string
  hero_race_id: string
  hero_class_id: string
  gender: string
  agility: number
  mental: number
  will: number
  strength: number
  armor: number
  is_published: boolean
  abilities: SelectedAbility[]
}>({
  name: {},
  lore_text: {},
  epic_quote: {},
  passive_name: {},
  passive_description: {},
  faction_id: '',
  hero_race_id: '',
  hero_class_id: '',
  gender: 'male',
  agility: 2,
  mental: 2,
  will: 2,
  strength: 2,
  armor: 2,
  is_published: false,
  abilities: [],
})

const title = computed(() => (props.mode === 'create' ? t('heroes.new') : t('heroes.edit')))
// Iconos con URL para los editores wysiwyg (gestor de Iconos del motor).
const iconList = computed(() =>
  icons.icons.filter((i) => i.url).map((i) => ({ name: i.name, url: i.url as string })),
)

/** Etiqueta traducida de una opción en el locale activo. */
function optionLabel(option: { id: number; name: Translations }): string {
  return option.name?.[locales.current] || Object.values(option.name || {})[0] || `#${option.id}`
}

// Opción vacía explícita: permite volver a "sin valor" (placeholder es disabled).
const factionOptions = computed(() => [
  { value: '', label: t('heroes.fields.noFaction') },
  ...factions.value.map((o) => ({ value: o.id, label: optionLabel(o) })),
])
const raceOptions = computed(() => [
  { value: '', label: t('heroes.fields.noRace') },
  ...races.value.map((o) => ({ value: o.id, label: optionLabel(o) })),
])
const classOptions = computed(() => [
  { value: '', label: t('heroes.fields.noClass') },
  ...classes.value.map((o) => ({ value: o.id, label: optionLabel(o) })),
])
const genderOptions = computed(() => [
  { value: 'male', label: t('heroes.genders.male') },
  { value: 'female', label: t('heroes.genders.female') },
])

// --- Atributos: límites de la configuración + derivados en vivo ---
const minAttr = computed(() => config.value?.min_attribute_value ?? 1)
const maxAttr = computed(() => config.value?.max_attribute_value ?? 5)
const totalAttributes = computed(
  () => form.agility + form.mental + form.will + form.strength + form.armor,
)
const totalOk = computed(() => {
  if (!config.value) return true
  return (
    totalAttributes.value >= config.value.min_total_attributes &&
    totalAttributes.value <= config.value.max_total_attributes
  )
})
// Vida calculada con la misma fórmula que el servidor (solo informativa).
const health = computed(() => {
  if (!config.value) return null
  const c = config.value
  return Math.max(
    1,
    c.total_health_base +
      form.agility * c.agility_multiplier +
      form.mental * c.mental_multiplier +
      form.will * c.will_multiplier +
      form.strength * c.strength_multiplier +
      form.armor * c.armor_multiplier,
  )
})

// --- Habilidades activas: combobox con búsqueda + lista ordenable ---

/** Metadatos de ataque en orden canónico rango → tipo → subtipo. */
function abilityMeta(a: AbilityOption | SelectedAbility): string {
  const parts: string[] = []
  if (a.range) parts.push(optionLabel(a.range))
  if (a.attack_type) parts.push(t(`heroAbilities.attackTypes.${a.attack_type}`))
  if (a.subtype) parts.push(optionLabel(a.subtype))
  return parts.join(' · ')
}

// Opciones del combobox: la búsqueda cubre nombre, metadatos y coste.
const availableAbilities = computed(() =>
  abilityOptions.value
    .filter((o) => !form.abilities.some((a) => a.id === o.id))
    .map((o) => ({
      id: o.id,
      label: optionLabel(o),
      search: [optionLabel(o), abilityMeta(o), o.cost].filter(Boolean).join(' '),
      ability: o,
    })),
)

// Al elegir en el combobox se añade directamente (sin botón intermedio).
function addAbility(id: number | string) {
  const option = abilityOptions.value.find((o) => o.id === Number(id))
  if (!option || form.abilities.some((a) => a.id === option.id)) return
  form.abilities.push({
    id: option.id,
    name: option.name,
    cost: option.cost,
    attack_type: option.attack_type ?? null,
    range: option.range ?? null,
    subtype: option.subtype ?? null,
  })
}

function removeAbility(index: number) {
  form.abilities.splice(index, 1)
}

function moveAbility(index: number, delta: number) {
  const to = index + delta
  if (to < 0 || to >= form.abilities.length) return
  const [item] = form.abilities.splice(index, 1)
  form.abilities.splice(to, 0, item)
}

// ¿Tiene el nombre algún valor en cualquier idioma?
const hasName = () => Object.values(form.name).some((v) => v && v.trim() !== '')

function reset() {
  form.name = {}
  form.lore_text = {}
  form.epic_quote = {}
  form.passive_name = {}
  form.passive_description = {}
  form.faction_id = ''
  form.hero_race_id = ''
  form.hero_class_id = ''
  form.gender = 'male'
  form.agility = 2
  form.mental = 2
  form.will = 2
  form.strength = 2
  form.armor = 2
  form.is_published = false
  form.abilities = []
  image.value = null
  currentImage.value = null
  clearErrors()
}

// Al abrir: carga selectores y configuración; en edición, el héroe por slug.
watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    reset()
    try {
      const [f, r, c, a, cfg] = await Promise.all([
        api.get('/admin/factions/options'),
        api.get('/admin/hero-races/options'),
        api.get('/admin/hero-classes/options'),
        api.get('/admin/hero-abilities/options'),
        api.get('/admin/hero-attributes-configuration'),
        locales.load(),
        icons.load(),
      ])
      factions.value = f.data.data
      races.value = r.data.data
      classes.value = c.data.data
      abilityOptions.value = a.data.data
      config.value = cfg.data.data
    } catch {
      toast.danger(t('common.errors.load'))
    }
    if (props.mode === 'edit' && props.targetSlug) {
      try {
        const hero = await find(props.targetSlug)
        form.name = hero.name ?? {}
        form.lore_text = hero.lore_text ?? {}
        form.epic_quote = hero.epic_quote ?? {}
        form.passive_name = hero.passive_name ?? {}
        form.passive_description = hero.passive_description ?? {}
        form.faction_id = hero.faction_id ? String(hero.faction_id) : ''
        form.hero_race_id = hero.hero_race_id ? String(hero.hero_race_id) : ''
        form.hero_class_id = hero.hero_class_id ? String(hero.hero_class_id) : ''
        form.gender = hero.gender ?? 'male'
        form.agility = hero.agility
        form.mental = hero.mental
        form.will = hero.will
        form.strength = hero.strength
        form.armor = hero.armor
        form.is_published = !!hero.is_published
        // Ya llegan ordenadas por position desde la API; los metadatos de
        // ataque se completan desde las opciones ya cargadas (por id).
        form.abilities = (hero.abilities ?? []).map((a) => {
          const option = abilityOptions.value.find((o) => o.id === a.id)
          return {
            id: a.id,
            name: a.name,
            cost: a.cost,
            attack_type: option?.attack_type ?? a.attack_type ?? null,
            range: option?.range ?? null,
            subtype: option?.subtype ?? null,
          }
        })
        currentImage.value = hero.image ?? null
      } catch {
        toast.danger(t('heroes.toast.saveError'))
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
    ['passive_name', form.passive_name],
    ['passive_description', form.passive_description],
  ]
  for (const [field, values] of translatables) {
    for (const [locale, value] of Object.entries(values))
      fd.append(`${field}[${locale}]`, value ?? '')
  }
  fd.append('faction_id', form.faction_id)
  fd.append('hero_race_id', form.hero_race_id)
  fd.append('hero_class_id', form.hero_class_id)
  fd.append('gender', form.gender)
  fd.append('agility', String(form.agility))
  fd.append('mental', String(form.mental))
  fd.append('will', String(form.will))
  fd.append('strength', String(form.strength))
  fd.append('armor', String(form.armor))
  fd.append('is_published', form.is_published ? '1' : '0')
  // Habilidades como array [{id, position}], 1-based según el orden visual.
  form.abilities.forEach((ability, index) => {
    fd.append(`abilities[${index}][id]`, String(ability.id))
    fd.append(`abilities[${index}][position]`, String(index + 1))
  })
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
  saving.value = true
  try {
    if (props.mode === 'edit' && props.targetSlug) {
      await updateForm(props.targetSlug, toFormData())
      toast.success(t('heroes.toast.updated'))
    } else {
      await createForm(toFormData())
      toast.success(t('heroes.toast.created'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    mapServerErrors(e)
    toast.danger(t('heroes.toast.saveError'))
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
    <fieldset class="hero-form__fieldset">
      <legend>{{ t('heroes.sections.basic') }}</legend>
      <TranslatableInput
        v-model="form.name"
        :locales="locales.locales"
        :label="t('heroes.fields.name')"
        required
        :error="errors.name"
      />
      <div class="hero-form__grid">
        <BaseSelect
          v-model="form.faction_id"
          :label="t('heroes.fields.faction')"
          :options="factionOptions"
          :error="errors.faction_id"
        />
        <BaseSelect
          v-model="form.hero_race_id"
          :label="t('heroes.fields.race')"
          :options="raceOptions"
          :error="errors.hero_race_id"
        />
        <BaseSelect
          v-model="form.hero_class_id"
          :label="t('heroes.fields.class')"
          :options="classOptions"
          :error="errors.hero_class_id"
        />
        <BaseSelect
          v-model="form.gender"
          :label="t('heroes.fields.gender')"
          :options="genderOptions"
          :error="errors.gender"
        />
      </div>
      <BaseCheckbox v-model="form.is_published" :label="t('heroes.fields.published')" />
      <ImageUpload
        v-model="image"
        :current-url="currentImage"
        :label="t('heroes.fields.image')"
        :drag-text="t('common.imageDrag')"
        :hint-text="t('common.imageHint')"
        :too-large-text="t('common.fileTooLarge')"
        :invalid-type-text="t('common.fileType')"
      />
    </fieldset>

    <!-- Atributos -->
    <fieldset class="hero-form__fieldset">
      <legend>{{ t('heroes.sections.attributes') }}</legend>
      <div class="hero-form__grid hero-form__grid--attributes">
        <NumericInput
          v-model="form.agility"
          :label="t('heroes.attributes.agility')"
          :min="minAttr"
          :max="maxAttr"
          :error="errors.agility"
        />
        <NumericInput
          v-model="form.mental"
          :label="t('heroes.attributes.mental')"
          :min="minAttr"
          :max="maxAttr"
          :error="errors.mental"
        />
        <NumericInput
          v-model="form.will"
          :label="t('heroes.attributes.will')"
          :min="minAttr"
          :max="maxAttr"
          :error="errors.will"
        />
        <NumericInput
          v-model="form.strength"
          :label="t('heroes.attributes.strength')"
          :min="minAttr"
          :max="maxAttr"
          :error="errors.strength"
        />
        <NumericInput
          v-model="form.armor"
          :label="t('heroes.attributes.armor')"
          :min="minAttr"
          :max="maxAttr"
          :error="errors.armor"
        />
      </div>
      <!-- Total y vida derivada, en vivo (misma fórmula que el servidor) -->
      <p class="hero-form__derived" :class="{ 'is-warning': !totalOk }">
        <span>
          {{ t('heroes.fields.totalAttributes') }}: <strong>{{ totalAttributes }}</strong>
          <template v-if="config">
            ({{ config.min_total_attributes }}–{{ config.max_total_attributes }})
          </template>
        </span>
        <span v-if="health !== null">
          {{ t('heroes.attributes.health') }}: <strong>{{ health }}</strong>
        </span>
      </p>
    </fieldset>

    <!-- Pasiva -->
    <fieldset class="hero-form__fieldset">
      <legend>{{ t('heroes.sections.passive') }}</legend>
      <TranslatableInput
        v-model="form.passive_name"
        :locales="locales.locales"
        :label="t('heroes.fields.passiveName')"
        :error="errors.passive_name"
      />
      <TranslatableInput
        v-model="form.passive_description"
        :locales="locales.locales"
        :label="t('heroes.fields.passiveDescription')"
        type="wysiwyg"
        :icons="iconList"
        :rich-labels="editorLabels"
        :error="errors.passive_description"
      />
    </fieldset>

    <!-- Habilidades activas (ordenables) -->
    <fieldset class="hero-form__fieldset">
      <legend>{{ t('heroes.sections.abilities') }}</legend>
      <!-- Combobox con búsqueda: al elegir, la habilidad se añade a la lista -->
      <SearchCombobox
        :model-value="null"
        :options="availableAbilities"
        :label="t('heroes.fields.abilities')"
        :placeholder="t('heroes.fields.selectAbility')"
        :search-placeholder="t('common.search')"
        :no-results="t('common.empty')"
        :error="errors.abilities"
        @update:model-value="addAbility"
      >
        <template #option="{ option }">
          <span class="ability-option">
            <span class="ability-option__name">{{ option.label }}</span>
            <span v-if="abilityMeta(option.ability)" class="ability-option__meta">
              {{ abilityMeta(option.ability) }}
            </span>
            <CostDice
              v-if="option.ability.cost"
              class="ability-option__cost"
              :cost="option.ability.cost"
            />
          </span>
        </template>
      </SearchCombobox>
      <p v-if="!form.abilities.length" class="hero-form__hint">
        {{ t('heroes.fields.noAbilities') }}
      </p>
      <ol v-else class="hero-form__abilities">
        <li v-for="(ability, index) in form.abilities" :key="ability.id">
          <span class="ability-option">
            <span class="ability-option__name">{{ optionLabel(ability) }}</span>
            <span v-if="abilityMeta(ability)" class="ability-option__meta">
              {{ abilityMeta(ability) }}
            </span>
            <CostDice v-if="ability.cost" class="ability-option__cost" :cost="ability.cost" />
          </span>
          <span class="hero-form__ability-actions">
            <button
              type="button"
              :disabled="index === 0"
              :aria-label="t('heroes.fields.moveUp')"
              :title="t('heroes.fields.moveUp')"
              @click="moveAbility(index, -1)"
            >
              <ArrowUp :size="14" />
            </button>
            <button
              type="button"
              :disabled="index === form.abilities.length - 1"
              :aria-label="t('heroes.fields.moveDown')"
              :title="t('heroes.fields.moveDown')"
              @click="moveAbility(index, 1)"
            >
              <ArrowDown :size="14" />
            </button>
            <button
              type="button"
              :aria-label="t('heroes.fields.removeAbility')"
              :title="t('heroes.fields.removeAbility')"
              @click="removeAbility(index)"
            >
              <X :size="14" />
            </button>
          </span>
        </li>
      </ol>
    </fieldset>

    <!-- Trasfondo -->
    <fieldset class="hero-form__fieldset">
      <legend>{{ t('heroes.sections.lore') }}</legend>
      <TranslatableInput
        v-model="form.lore_text"
        :locales="locales.locales"
        :label="t('heroes.fields.loreText')"
        type="wysiwyg"
        :icons="iconList"
        :rich-labels="editorLabels"
        :error="errors.lore_text"
      />
      <TranslatableInput
        v-model="form.epic_quote"
        :locales="locales.locales"
        :label="t('heroes.fields.epicQuote')"
        type="wysiwyg"
        :icons="iconList"
        :rich-labels="editorLabels"
        :error="errors.epic_quote"
      />
    </fieldset>
  </EditModal>
</template>
