<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { LogIn, User } from '@lucide/vue'
import { useI18n } from 'vue-i18n'
import { MotorBadge, BaseButton, PageBackground, useHead } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { blockRegistry } from '@/blocks/registry'
import { templateFor } from '@/templates/registry'
import { useAuthStore } from '@/stores/auth'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

// Si el CRM tiene una home publicada, la home ES esa página (doc 03); el
// contenido de siempre queda de fallback mientras no la haya.
interface HomePage {
  template: string | null
  background_image: string | null
  meta: { title: string; description: string }
  blocks: {
    id: number
    component: string
    settings: Record<string, unknown>
    data: Record<string, unknown>
  }[]
}

const { t } = useI18n()
const locales = useLocalesStore()
const site = useSiteStore()
const homePage = ref<HomePage | null>(null)

async function loadHome() {
  try {
    await site.load() // el head usa documentTitle: sin carreras en el prerender
    const { data } = await api.get('/pages/home')
    homePage.value = data.data
  } catch {
    homePage.value = null // sin home del CRM: fallback
  }

  // SEO (doc 10): la home canónica es la raíz de cada locale.
  const origin = window.location.origin
  useHead({
    title: site.documentTitle(homePage.value?.meta.title),
    description: homePage.value?.meta.description || site.description || undefined,
    canonical: `${origin}/${locales.current}`,
    alternates: Object.fromEntries(locales.locales.map((l) => [l.code, `${origin}/${l.code}`])),
  })
}

watch(() => locales.current, loadHome)

interface Ping {
  name: string
  version: string
  default_locale: string
  locales: string[]
}

const auth = useAuthStore()
const ping = ref<Ping | null>(null)
const error = ref<string | null>(null)

onMounted(async () => {
  await locales.load()
  await loadHome()
  try {
    const { data } = await api.get('/motor/ping')
    ping.value = data
  } catch (e) {
    error.value = String(e)
  }
})
</script>

<template>
  <!-- Home del CRM (si la hay), con su plantilla y su imagen de fondo -->
  <template v-if="homePage">
    <PageBackground :image="homePage.background_image" />
    <component :is="templateFor(homePage.template)">
      <component
        :is="blockRegistry[block.component]"
        v-for="block in homePage.blocks.filter((b) => blockRegistry[b.component])"
        :key="block.id"
        :settings="block.settings"
        :data="block.data"
      />
    </component>
  </template>

  <main v-else class="home">
    <MotorBadge label="EdC" :version="ping?.version ?? ''" />
    <h1>Web pública</h1>
    <p v-if="auth.user">
      Hola, <strong>{{ auth.user.name }}</strong
      >.
    </p>

    <section v-if="ping" class="card">
      <h2>{{ ping.name }}</h2>
      <ul>
        <li>
          Versión del motor: <strong>{{ ping.version }}</strong>
        </li>
        <li>
          Locale por defecto: <strong>{{ ping.default_locale }}</strong>
        </li>
        <li>
          Locales: <strong>{{ ping.locales.join(', ') }}</strong>
        </li>
      </ul>
    </section>
    <p v-else-if="error" class="error">No conecta con la API: {{ error }}</p>

    <RouterLink
      v-if="!auth.isAuthenticated"
      :to="{ name: 'login', params: { locale: locales.current } }"
      ><BaseButton>
        <template #icon><LogIn :size="16" /></template>
        {{ t('nav.login') }}
      </BaseButton></RouterLink
    >
    <RouterLink v-else :to="{ name: 'account', params: { locale: locales.current } }"
      ><BaseButton>
        <template #icon><User :size="16" /></template>
        {{ t('nav.account') }}
      </BaseButton></RouterLink
    >
  </main>
</template>
