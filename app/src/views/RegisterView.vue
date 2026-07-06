<script setup lang="ts">
import { ref } from 'vue'
import { UserPlus } from '@lucide/vue'
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
const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const privacy = ref(false)
const error = ref<string | null>(null)
const loading = ref(false)

async function submit() {
  error.value = null
  loading.value = true
  try {
    await auth.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
      privacy: privacy.value,
    })
    router.push({ name: 'account', params: { locale: locales.current } })
  } catch (e) {
    error.value = apiMessage(e, t('auth.register.error'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="auth">
    <h1>{{ t('auth.register.title') }}</h1>
    <form class="form" @submit.prevent="submit">
      <div class="field">
        <label>{{ t('auth.register.name') }}</label>
        <input v-model="name" type="text" required />
      </div>
      <div class="field">
        <label>{{ t('auth.login.email') }}</label>
        <input v-model="email" type="email" required autocomplete="email" />
      </div>
      <div class="field">
        <label>{{ t('auth.login.password') }}</label>
        <input v-model="password" type="password" required autocomplete="new-password" />
      </div>
      <div class="field">
        <label>{{ t('auth.register.password2') }}</label>
        <input
          v-model="passwordConfirmation"
          type="password"
          required
          autocomplete="new-password"
        />
      </div>
      <!-- Protección de datos: sin aceptar no hay registro (backend igual) -->
      <div class="field field--privacy">
        <p class="privacy-note">{{ t('auth.register.privacyNote') }}</p>
        <label class="privacy-check">
          <input v-model="privacy" type="checkbox" required />
          {{ t('auth.register.privacyAccept') }}
        </label>
      </div>

      <p v-if="error" class="error">{{ error }}</p>
      <BaseButton type="submit" :disabled="!privacy">
        <template #icon><UserPlus :size="16" /></template>
        {{ loading ? t('auth.register.submitting') : t('auth.register.submit') }}
      </BaseButton>
    </form>
    <p class="hint">
      {{ t('auth.register.hasAccount') }}
      <RouterLink :to="{ name: 'login', params: { locale: locales.current } }">{{
        t('auth.register.login')
      }}</RouterLink
      >.
    </p>
  </main>
</template>
