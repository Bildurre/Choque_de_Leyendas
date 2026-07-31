<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { SquarePen } from '@lucide/vue'
import { BaseButton, EditModal, NumericInput, useToast } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import type { HeroAttributesConfig } from '@juego/shared'
import InfoBlock from '@/components/InfoBlock.vue'

// Configuración de atributos de héroe (singleton): dos cards en modo LECTURA
// (límites por atributo/total y fórmula de vida) con botón Editar que abre
// un modal solo con los campos de su sección. El PUT valida el singleton
// ENTERO: al guardar viajan los valores editados mezclados sobre los actuales.
const { t } = useI18n()
const toast = useToast()

const loading = ref(true)
const saving = ref(false)
const errors = reactive<Record<string, string>>({})

const config = reactive<HeroAttributesConfig>({
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

// --- Modal de edición por sección (una a la vez) ---
type Section = 'limits' | 'health'
const editing = ref<Section | null>(null)
// El formulario del modal parte de una COPIA completa de la configuración:
// el usuario toca solo su sección y el PUT viaja con el resto intacto.
const form = reactive<HeroAttributesConfig>({ ...config })

const modalOpen = computed({
  get: () => editing.value !== null,
  set: (open: boolean) => {
    if (!open) editing.value = null
  },
})
const modalFields = computed(() => (editing.value === 'health' ? healthFields : limitFields))
const modalTitle = computed(() =>
  editing.value ? t(`heroAttributesConfig.modal.${editing.value}`) : '',
)

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}

function openEdit(section: Section) {
  clearErrors()
  Object.assign(form, config)
  editing.value = section
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/hero-attributes-configuration')
    Object.assign(config, data.data)
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
    // Recarga los valores con lo persistido y cierra el modal.
    Object.assign(config, data.data)
    toast.success(t('heroAttributesConfig.toast.saved'))
    editing.value = null
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
    <!-- Las dos cards comparten fila mientras quepan (auto-fit) -->
    <div class="hero-attributes-config__cards">
      <!-- Límites de atributos -->
      <InfoBlock :title="t('heroAttributesConfig.sections.limits')">
        <template #actions>
          <BaseButton variant="info" @click="openEdit('limits')">
            <template #icon><SquarePen :size="14" /></template>
            {{ t('common.actions.edit') }}
          </BaseButton>
        </template>
        <p class="hero-attributes-config__hint">
          {{ t('heroAttributesConfig.sections.limitsHint') }}
        </p>
        <dl class="info-list">
          <template v-for="field in limitFields" :key="field.key">
            <dt>{{ t(`heroAttributesConfig.fields.${field.key}`) }}</dt>
            <dd>{{ config[field.key] }}</dd>
          </template>
        </dl>
      </InfoBlock>

      <!-- Cálculo de vida -->
      <InfoBlock :title="t('heroAttributesConfig.sections.health')">
        <template #actions>
          <BaseButton variant="info" @click="openEdit('health')">
            <template #icon><SquarePen :size="14" /></template>
            {{ t('common.actions.edit') }}
          </BaseButton>
        </template>
        <p class="hero-attributes-config__hint">
          {{ t('heroAttributesConfig.sections.healthHint') }}
        </p>
        <dl class="info-list">
          <template v-for="field in healthFields" :key="field.key">
            <dt>{{ t(`heroAttributesConfig.fields.${field.key}`) }}</dt>
            <dd>{{ config[field.key] }}</dd>
          </template>
        </dl>
      </InfoBlock>
    </div>

    <!-- Modal de la sección en edición: solo sus campos numéricos -->
    <EditModal
      v-model="modalOpen"
      :title="modalTitle"
      :loading="saving"
      :submit-label="t('common.save')"
      :cancel-label="t('common.cancel')"
      @submit="save"
    >
      <!-- Fila del sistema compartido: dos columnas FIJAS (los campos
           fluyen en filas de dos; en modal angosto apilan a una) -->
      <div class="form-row">
        <NumericInput
          v-for="field in modalFields"
          :key="field.key"
          v-model="form[field.key]"
          :label="t(`heroAttributesConfig.fields.${field.key}`)"
          :hint="t(`heroAttributesConfig.hints.${field.key}`)"
          :min="field.min"
          :max="field.max"
          :error="errors[field.key]"
        />
      </div>
    </EditModal>
  </div>
</template>
