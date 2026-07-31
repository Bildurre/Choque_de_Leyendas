<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Wand2 } from '@lucide/vue'
import { BaseButton, BaseInput, BaseSelect, EditModal, useToast } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useAuthStore } from '@/stores/auth'

// Alta/edición de usuario (doc 05): nombre, email, contraseña (opcional al
// editar) y rol. El propio rol no es editable (lo ignora también la API).
export interface UserRow {
  id: number
  name: string
  email: string
  roles: string[]
  email_verified: boolean
}

const props = defineProps<{ modelValue: boolean; user?: UserRow | null }>()
const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t, te } = useI18n()
const toast = useToast()
const auth = useAuthStore()

function roleLabel(role: string): string {
  return te(`users.roles.${role}`) ? t(`users.roles.${role}`) : role
}

const saving = ref(false)
const errors = ref<Record<string, string>>({})
const name = ref('')
const email = ref('')
const password = ref('')
// Confirmación de contraseña: validación SOLO en cliente (no viaja a la API,
// el backend del motor no la valida).
const passwordConfirm = ref('')
// Tras "Generar contraseña" ambos campos pasan a texto claro para poder
// copiarla; al reabrir el modal vuelven a ocultarse.
const showPassword = ref(false)
const role = ref('editor')

const ROLES = ['admin', 'editor', 'user']
const isSelf = computed(() => props.user?.id === auth.user?.id)
const passwordFieldType = computed(() => (showPassword.value ? 'text' : 'password'))

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    errors.value = {}
    name.value = props.user?.name ?? ''
    email.value = props.user?.email ?? ''
    password.value = ''
    passwordConfirm.value = ''
    showPassword.value = false
    role.value = props.user?.roles?.[0] ?? 'editor'
  },
)

/** Contraseña aleatoria de 16 caracteres (mayúsculas, minúsculas, dígitos y
 *  símbolos seguros) con crypto.getRandomValues, sin sesgo de módulo. */
function randomPassword(length = 16): string {
  const alphabet =
    'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789' + '!@#$%^&*()-_=+?'
  // Muestreo por rechazo: se descartan los bytes fuera del múltiplo del
  // tamaño del alfabeto para no sesgar la distribución.
  const limit = Math.floor(256 / alphabet.length) * alphabet.length
  let out = ''
  while (out.length < length) {
    const bytes = new Uint8Array(length * 2)
    crypto.getRandomValues(bytes)
    for (const byte of bytes) {
      if (out.length === length) break
      if (byte < limit) out += alphabet[byte % alphabet.length]
    }
  }
  return out
}

/** Genera una contraseña, la mete en ambos campos y la muestra en claro. */
function generatePassword() {
  const generated = randomPassword()
  password.value = generated
  passwordConfirm.value = generated
  showPassword.value = true
  delete errors.value.password
  delete errors.value.password_confirm
}

async function save() {
  errors.value = {}
  // Validación EN CLIENTE: si hay contraseña (o confirmación) escrita y no
  // coinciden, error de campo y NO se envía nada a la API.
  if ((password.value || passwordConfirm.value) && password.value !== passwordConfirm.value) {
    errors.value = { password_confirm: t('users.errors.passwordMismatch') }
    return
  }
  saving.value = true
  try {
    const payload = {
      name: name.value,
      email: email.value,
      password: password.value,
      role: role.value,
    }
    if (props.user) await api.put(`/admin/users/${props.user.id}`, payload)
    else await api.post('/admin/users', payload)
    toast.success(t('users.toast.saved'))
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.danger(t('users.toast.saveError'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <EditModal
    :model-value="modelValue"
    :title="user ? t('users.edit') : t('users.new')"
    :submit-label="t('common.save')"
    :cancel-label="t('common.cancel')"
    :loading="saving"
    @update:model-value="(v) => emit('update:modelValue', v)"
    @submit="save"
  >
    <!-- Filas del sistema compartido (.form-row): identidad y contraseñas
         en columnas mientras quepan (en un modal angosto apilan). -->
    <div class="form-row">
      <BaseInput v-model="name" :label="t('users.fields.name')" required :error="errors.name" />
      <BaseInput
        v-model="email"
        type="email"
        :label="t('users.fields.email')"
        required
        :error="errors.email"
      />
    </div>
    <div class="form-row">
      <BaseInput
        v-model="password"
        :type="passwordFieldType"
        :label="user ? t('users.fields.passwordOptional') : t('users.fields.password')"
        :required="!user"
        :error="errors.password"
      />
      <BaseInput
        v-model="passwordConfirm"
        :type="passwordFieldType"
        :label="t('users.fields.passwordConfirm')"
        :required="!user"
        :error="errors.password_confirm"
      />
    </div>
    <BaseButton variant="text" class="user-form__generate" @click="generatePassword">
      <template #icon><Wand2 :size="14" /></template>
      {{ t('users.actions.generatePassword') }}
    </BaseButton>
    <BaseSelect
      v-if="!isSelf"
      v-model="role"
      :label="t('users.fields.role')"
      :options="ROLES.map((r) => ({ value: r, label: roleLabel(r) }))"
      :error="errors.role"
    />
  </EditModal>
</template>
