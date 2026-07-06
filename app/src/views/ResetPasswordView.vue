<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { KeyRound } from '@lucide/vue'
import { useI18n } from 'vue-i18n'
import { BaseButton } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'
import { apiMessage } from '@/lib/apiError'

// Restablecer contraseña (doc 05): se llega desde el enlace del correo con
// ?token=…&email=… en la query.
const route = useRoute()
const { t } = useI18n()
const locales = useLocalesStore()

const token = computed(() => String(route.query.token ?? ''))
const email = ref(String(route.query.email ?? ''))
const password = ref('')
const passwordConfirmation = ref('')
const done = ref(false)
const error = ref<string | null>(null)
const loading = ref(false)

async function submit() {
  error.value = null
  loading.value = true
  try {
    await api.post('/auth/reset-password', {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    done.value = true
  } catch (e) {
    error.value = apiMessage(e, t('auth.reset.error'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="auth">
    <h1>{{ t('auth.reset.title') }}</h1>

    <template v-if="done">
      <p class="ok">{{ t('auth.reset.done') }}</p>
      <p class="hint">
        <RouterLink :to="{ name: 'login', params: { locale: locales.current } }">{{
          t('auth.login.submit')
        }}</RouterLink>
      </p>
    </template>

    <form v-else class="form" @submit.prevent="submit">
      <div class="field">
        <label>{{ t('auth.login.email') }}</label>
        <input v-model="email" type="email" required autocomplete="email" />
      </div>
      <div class="field">
        <label>{{ t('auth.reset.newPassword') }}</label>
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
      <p v-if="error" class="error">{{ error }}</p>
      <BaseButton type="submit" :disabled="loading">
        <template #icon><KeyRound :size="16" /></template>
        {{ loading ? t('auth.reset.submitting') : t('auth.reset.submit') }}
      </BaseButton>
    </form>
  </main>
</template>
