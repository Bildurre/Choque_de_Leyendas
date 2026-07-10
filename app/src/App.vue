<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '@/components/AppHeader.vue'
import { useAuthStore } from '@/stores/auth'
import { useLocalesStore } from '@/stores/locales'
import { useSiteStore } from '@/stores/site'

const auth = useAuthStore()
const route = useRoute()
const site = useSiteStore()
const locales = useLocalesStore()

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
  <!-- La cabecera es fija (CDL): el contenido le deja hueco arriba -->
  <div :class="{ 'site-content': !bare }">
    <RouterView />
  </div>
  <footer v-if="!bare && (site.footerText || site.title)" class="app-footer">
    <!-- El pie es texto rico saneado en el servidor (lista blanca) -->
    <div v-if="site.footerText" class="rich-content" v-html="site.footerText" />
    <span v-else>{{ site.title }}</span>
  </footer>
</template>
