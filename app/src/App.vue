<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { AppRightSidebar } from '@edc-motor/ui'
import AppHeader from '@/components/AppHeader.vue'
import { useAuthStore } from '@/stores/auth'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

const auth = useAuthStore()
const route = useRoute()
const site = useSiteStore()
const locales = useLocalesStore()
const { t } = useI18n()

// Las rutas "desnudas" (p. ej. /_render para la captura a PNG) van sin nav.
const bare = computed(() => route.meta.bare === true)

onMounted(async () => {
  if (auth.token && !auth.user) auth.fetchMe().catch(() => {})
  // La configuración usa el locale para título/pie: locales primero.
  await locales.load().catch(() => {})
  await site.load()
})
</script>

<template>
  <AppHeader v-if="!bare" />
  <!-- La cabecera es fija y siempre visible: el contenido le deja hueco
       arriba. La barra derecha contextual es FIJA (fuera del flujo), de
       justo bajo la cabecera hasta abajo: cada vista registra sus filtros
       con useAppRightSidebar() y Teleport a #app-right-sidebar-target; se
       abre/cierra con el asa anclada a la propia barra, y desplegada en
       ancho contenido y pie le hacen hueco (padding-right sobre
       .app-right-sidebar--docked, ver scss). -->
  <div v-if="!bare" class="site-main">
    <div class="site-content">
      <RouterView />
    </div>
    <AppRightSidebar
      :open-label="t('nav.filters')"
      :close-label="t('nav.closeFilters')"
      :fallback-title="t('nav.filters')"
    />
  </div>
  <RouterView v-else />
  <footer v-if="!bare && (site.footerText || site.title)" class="app-footer">
    <!-- eslint-disable-next-line vue/no-v-html -- pie saneado en servidor (lista blanca) -->
    <div v-if="site.footerText" class="rich-content" v-html="site.footerText" />
    <span v-else>{{ site.title }}</span>
  </footer>
</template>
