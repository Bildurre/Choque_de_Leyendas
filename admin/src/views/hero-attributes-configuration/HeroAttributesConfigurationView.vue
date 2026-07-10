<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Save } from '@lucide/vue'
import { BaseButton, NumericInput, useToast } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import type { HeroAttributesConfig } from '@juego/shared'

// Configuración de atributos de héroe (singleton, estilo SettingsView):
// límites por atributo y en total, y fórmula de vida (base + multiplicadores).
const { t } = useI18n()
const toast = useToast()

const loading = ref(true)
const saving = ref(false)
const errors = reactive<Record<string, string>>({})

const form = reactive<HeroAttributesConfig>({
  min_attribute_value: 1,
  max_attribute_value: 5,
  min_total_attributes: 12,
  max_total_attributes: 18,
  agility_multiplier: -1,
  mental_multiplier: -1,
  will_multiplier: 1,
  strength_multiplier: -1,
  armor_multiplier: 1,
  total_health_base: 25,
})

// Campos por sección (con los rangos de sanidad que valida el servidor).
const limitFields = [
  { key: 'min_attribute_value', min: 1, max: 3 },
  { key: 'max_attribute_value', min: 3, max: 10 },
  { key: 'min_total_attributes', min: 5, max: 20 },
  { key: 'max_total_attributes', min: 10, max: 50 },
] as const
const healthFields = [
  { key: 'total_health_base', min: 10, max: 100 },
  { key: 'agility_multiplier', min: -5, max: 5 },
  { key: 'mental_multiplier', min: -5, max: 5 },
  { key: 'will_multiplier', min: -5, max: 5 },
  { key: 'strength_multiplier', min: -5, max: 5 },
  { key: 'armor_multiplier', min: -5, max: 5 },
] as const

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/hero-attributes-configuration')
    Object.assign(form, data.data)
  } catch {
    toast.danger(t('common.errors.load'))
  } finally {
    loading.value = false
  }
}

async function save() {
  clearErrors()
  saving.value = true
  try {
    const { data } = await api.put('/admin/hero-attributes-configuration', { ...form })
    Object.assign(form, data.data)
    toast.success(t('heroAttributesConfig.toast.saved'))
  } catch (e) {
    for (const [k, v] of Object.entries(fieldErrors(e))) errors[k] = v
    toast.danger(t('heroAttributesConfig.toast.saveError'))
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div v-if="!loading" class="hero-attributes-config">
    <div class="list-view__top">
      <BaseButton :disabled="saving" @click="save">
        <template #icon><Save :size="16" /></template>
        {{ t('common.save') }}
      </BaseButton>
    </div>

    <div class="hero-attributes-config__columns">
      <!-- Límites de atributos -->
      <section class="hero-attributes-config__section">
        <h2>{{ t('heroAttributesConfig.sections.limits') }}</h2>
        <p class="hero-attributes-config__hint">
          {{ t('heroAttributesConfig.sections.limitsHint') }}
        </p>
        <div class="hero-attributes-config__grid">
          <NumericInput
            v-for="field in limitFields"
            :key="field.key"
            v-model="form[field.key]"
            :label="t(`heroAttributesConfig.fields.${field.key}`)"
            :hint="t(`heroAttributesConfig.hints.${field.key}`)"
            :min="field.min"
            :max="field.max"
            :error="errors[field.key]"
          />
        </div>
      </section>

      <!-- Cálculo de vida -->
      <section class="hero-attributes-config__section">
        <h2>{{ t('heroAttributesConfig.sections.health') }}</h2>
        <p class="hero-attributes-config__hint">
          {{ t('heroAttributesConfig.sections.healthHint') }}
        </p>
        <div class="hero-attributes-config__grid">
          <NumericInput
            v-for="field in healthFields"
            :key="field.key"
            v-model="form[field.key]"
            :label="t(`heroAttributesConfig.fields.${field.key}`)"
            :hint="t(`heroAttributesConfig.hints.${field.key}`)"
            :min="field.min"
            :max="field.max"
            :error="errors[field.key]"
          />
        </div>
      </section>
    </div>
  </div>
</template>
