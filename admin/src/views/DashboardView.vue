<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface Ping {
  name: string
  version: string
  locales: string[]
}
const { t } = useI18n()
const auth = useAuthStore()
const ping = ref<Ping | null>(null)

onMounted(async () => {
  try {
    const { data } = await api.get('/motor/ping')
    ping.value = data
  } catch {
    /* endpoint opcional */
  }
})
</script>

<template>
  <p v-if="auth.user">
    {{ t('dashboard.connectedAs', { name: auth.user.name, roles: auth.user.roles.join(', ') }) }}
  </p>
  <p v-if="ping">
    {{
      t('dashboard.motor', {
        name: ping.name,
        version: ping.version,
        locales: ping.locales.join(', '),
      })
    }}
  </p>
</template>
