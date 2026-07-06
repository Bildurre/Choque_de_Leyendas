<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { LogIn } from '@lucide/vue'
import { BaseButton, MotorBadge } from '@edc-motor/ui'
import { useAuthStore } from '@/stores/auth'
import { apiMessage } from '@/lib/apiError'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(route.query.denied ? t('login.denied') : null)

async function submit() {
  error.value = null
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    if (!auth.canAccessAdmin) {
      await auth.logout()
      error.value = t('login.noAccess')
      return
    }
    router.push({ name: 'dashboard' })
  } catch (e) {
    error.value = apiMessage(e, t('login.failed'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login">
    <div class="login__box">
      <MotorBadge label="EdC Admin" />
      <h1>{{ t('login.title') }}</h1>
      <form class="form" @submit.prevent="submit">
        <div class="field">
          <label>{{ t('login.email') }}</label
          ><input v-model="email" type="email" required autocomplete="email" />
        </div>
        <div class="field">
          <label>{{ t('login.password') }}</label
          ><input v-model="password" type="password" required autocomplete="current-password" />
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <BaseButton type="submit">
          <template #icon><LogIn :size="16" /></template>
          {{ loading ? t('login.submitting') : t('login.submit') }}
        </BaseButton>
      </form>
    </div>
  </main>
</template>
