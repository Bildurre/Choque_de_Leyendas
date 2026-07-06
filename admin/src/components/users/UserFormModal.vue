<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BaseInput, BaseSelect, EditModal, useToast } from '@edc-motor/ui'
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
const role = ref('editor')

const ROLES = ['admin', 'editor', 'user']
const isSelf = computed(() => props.user?.id === auth.user?.id)

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    errors.value = {}
    name.value = props.user?.name ?? ''
    email.value = props.user?.email ?? ''
    password.value = ''
    role.value = props.user?.roles?.[0] ?? 'editor'
  },
)

async function save() {
  saving.value = true
  errors.value = {}
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
    <BaseInput v-model="name" :label="t('users.fields.name')" required :error="errors.name" />
    <BaseInput
      v-model="email"
      type="email"
      :label="t('users.fields.email')"
      required
      :error="errors.email"
    />
    <BaseInput
      v-model="password"
      type="password"
      :label="user ? t('users.fields.passwordOptional') : t('users.fields.password')"
      :required="!user"
      :error="errors.password"
    />
    <BaseSelect
      v-if="!isSelf"
      v-model="role"
      :label="t('users.fields.role')"
      :options="ROLES.map((r) => ({ value: r, label: roleLabel(r) }))"
      :error="errors.role"
    />
  </EditModal>
</template>
