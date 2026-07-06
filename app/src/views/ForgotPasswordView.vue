<script setup lang="ts">
import { ref } from 'vue'
import { MailQuestion } from '@lucide/vue'
import { useI18n } from 'vue-i18n'
import { BaseButton } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'
import { apiMessage } from '@/lib/apiError'

// Recuperación de contraseña (doc 05): pide el enlace por email.
const { t } = useI18n()
const locales = useLocalesStore()
const email = ref('')
const message = ref<string | null>(null)
const error = ref<string | null>(null)
const loading = ref(false)

async function submit() {
  message.value = error.value = null
  loading.value = true
  try {
    const { data } = await api.post('/auth/forgot-password', { email: email.value })
    message.value = data.message
  } catch (e) {
    error.value = apiMessage(e, t('auth.forgot.error'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="auth">
    <h1>{{ t('auth.forgot.title') }}</h1>
    <p class="hint">{{ t('auth.forgot.intro') }}</p>
    <form class="form" @submit.prevent="submit">
      <div class="field">
        <label>{{ t('auth.login.email') }}</label>
        <input v-model="email" type="email" required autocomplete="email" />
      </div>
      <p v-if="message" class="ok">{{ message }}</p>
      <p v-if="error" class="error">{{ error }}</p>
      <BaseButton type="submit" :disabled="loading">
        <template #icon><MailQuestion :size="16" /></template>
        {{ loading ? t('auth.forgot.submitting') : t('auth.forgot.submit') }}
      </BaseButton>
    </form>
    <p class="hint">
      <RouterLink :to="{ name: 'login', params: { locale: locales.current } }">{{
        t('auth.forgot.backToLogin')
      }}</RouterLink>
    </p>
  </main>
</template>
