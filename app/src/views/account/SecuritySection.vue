<script setup lang="ts">
import { ref } from 'vue'
import { KeyRound } from '@lucide/vue'
import { useI18n } from 'vue-i18n'
import { BaseButton } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { apiMessage } from '@/lib/apiError'

// Sección base del panel (doc 05): cambio de contraseña.
const { t } = useI18n()
const currentPassword = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const message = ref<string | null>(null)
const error = ref<string | null>(null)

async function save() {
  message.value = error.value = null
  try {
    await api.put('/account/password', {
      current_password: currentPassword.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    message.value = t('account.security.ok')
    currentPassword.value = password.value = passwordConfirmation.value = ''
  } catch (e) {
    error.value = apiMessage(e, t('account.security.error'))
  }
}
</script>

<template>
  <div class="auth auth--section">
    <h2>{{ t('account.sections.security') }}</h2>
    <form class="form" @submit.prevent="save">
      <div class="field">
        <label>{{ t('account.security.current') }}</label
        ><input v-model="currentPassword" type="password" required />
      </div>
      <div class="field">
        <label>{{ t('account.security.new') }}</label
        ><input v-model="password" type="password" required />
      </div>
      <div class="field">
        <label>{{ t('account.security.repeat') }}</label
        ><input v-model="passwordConfirmation" type="password" required />
      </div>
      <p v-if="message" class="ok">{{ message }}</p>
      <p v-if="error" class="error">{{ error }}</p>
      <BaseButton type="submit">
        <template #icon><KeyRound :size="16" /></template>
        {{ t('account.security.submit') }}
      </BaseButton>
    </form>
  </div>
</template>
