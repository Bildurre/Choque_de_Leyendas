<script setup lang="ts">
import { ref } from 'vue'
import { LogIn } from '@lucide/vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { BaseButton } from '@edc-motor/ui'
import { useAuthStore } from '@/stores/auth'
import { useLocalesStore } from '@/stores/locales'
import { apiMessage } from '@/lib/apiError'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const locales = useLocalesStore()
const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const loading = ref(false)

async function submit() {
  error.value = null
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    router.push({ name: 'account', params: { locale: locales.current } })
  } catch (e) {
    error.value = apiMessage(e, t('auth.login.error'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="auth">
    <h1>{{ t('auth.login.title') }}</h1>
    <form class="form" @submit.prevent="submit">
      <div class="field">
        <label>{{ t('auth.login.email') }}</label>
        <input v-model="email" type="email" required autocomplete="email" />
      </div>
      <div class="field">
        <label>{{ t('auth.login.password') }}</label>
        <input v-model="password" type="password" required autocomplete="current-password" />
      </div>
      <p v-if="error" class="error">{{ error }}</p>
      <BaseButton type="submit">
        <template #icon><LogIn :size="16" /></template>
        {{ loading ? t('auth.login.submitting') : t('auth.login.submit') }}
      </BaseButton>
    </form>
    <p class="hint">
      <RouterLink :to="{ name: 'forgot', params: { locale: locales.current } }">{{
        t('auth.login.forgot')
      }}</RouterLink>
    </p>
    <p class="hint">
      {{ t('auth.login.noAccount') }}
      <RouterLink :to="{ name: 'register', params: { locale: locales.current } }">{{
        t('auth.login.register')
      }}</RouterLink
      >.
    </p>
  </main>
</template>
