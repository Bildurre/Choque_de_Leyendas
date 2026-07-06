<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { MailCheck, Save } from '@lucide/vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { BaseButton } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { apiMessage } from '@/lib/apiError'

// Sección base del panel (doc 05): nombre y email + verificación (DC-14).
const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const name = ref('')
const email = ref('')
const message = ref<string | null>(null)
const error = ref<string | null>(null)

// Se llega con ?verified=1 desde el enlace del correo de verificación.
const justVerified = computed(() => route.query.verified === '1')
const needsVerification = computed(() => !!auth.user && !auth.user.email_verified)

onMounted(async () => {
  // Tras verificar, refresca los datos aunque ya estuvieran cargados.
  if (!auth.user || justVerified.value) await auth.fetchMe().catch(() => {})
  name.value = auth.user?.name ?? ''
  email.value = auth.user?.email ?? ''
})

async function save() {
  message.value = error.value = null
  try {
    const { data } = await api.put('/account', { name: name.value, email: email.value })
    auth.user = data.data
    message.value = t('account.profile.saved')
  } catch (e) {
    error.value = apiMessage(e, t('account.profile.saveError'))
  }
}

const resending = ref(false)
async function resendVerification() {
  message.value = error.value = null
  resending.value = true
  try {
    const { data } = await api.post('/auth/email/verification-notification')
    message.value = data.message ?? t('account.profile.resendOk')
  } catch (e) {
    error.value = apiMessage(e, t('account.profile.resendError'))
  } finally {
    resending.value = false
  }
}
</script>

<template>
  <div class="auth auth--section">
    <h2>{{ t('account.sections.profile') }}</h2>
    <p v-if="auth.user" class="roles">
      {{ t('account.profile.role') }}: <strong>{{ auth.user.roles.join(', ') }}</strong>
    </p>

    <p v-if="justVerified && auth.user?.email_verified" class="ok">
      {{ t('account.profile.justVerified') }}
    </p>
    <div v-else-if="needsVerification" class="verify-notice">
      <p>{{ t('account.profile.unverified', { email: auth.user?.email }) }}</p>
      <BaseButton variant="secondary" :disabled="resending" @click="resendVerification">
        <template #icon><MailCheck :size="16" /></template>
        {{ t('account.profile.resend') }}
      </BaseButton>
    </div>

    <form class="form" @submit.prevent="save">
      <div class="field">
        <label>{{ t('account.profile.name') }}</label
        ><input v-model="name" type="text" required />
      </div>
      <div class="field">
        <label>{{ t('account.profile.email') }}</label
        ><input v-model="email" type="email" required />
      </div>
      <p v-if="message" class="ok">{{ message }}</p>
      <p v-if="error" class="error">{{ error }}</p>
      <BaseButton type="submit">
        <template #icon><Save :size="16" /></template>
        {{ t('account.profile.save') }}
      </BaseButton>
    </form>
  </div>
</template>
