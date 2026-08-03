<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { AppRightSidebar, PageBackground, ToastContainer } from '@edc-motor/ui'
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

// Fondo configurable de las herramientas (site settings, mapa
// `index_backgrounds`): se monta UNA vez aquí para el contador de vidas y
// el lanzador de dados (la clave `errors` la montan las vistas de error).
// Fuera de ellas devuelve null: los índices se componen ahora desde el CRM
// (el fondo lo pone la propia página), y home, páginas del CRM y detalles
// ya montan su propio PageBackground en sus vistas (no debe haber doble
// fondo).
const indexBgImage = computed(() => {
  const name = route.name
  if (name === 'life-counter' || name === 'dice-roller') {
    return site.indexBackground(String(name))
  }
  return null
})

onMounted(async () => {
  if (auth.token && !auth.user) auth.fetchMe().catch(() => {})
  // La configuración usa el locale para título/pie: locales primero.
  await locales.load().catch(() => {})
  await site.load()
})
</script>

<template>
  <AppHeader v-if="!bare" />
  <!-- Fondo de las páginas índice (capa fija tras el contenido): solo se
       monta cuando la ruta es un índice CON fondo configurado. -->
  <PageBackground v-if="indexBgImage" :image="indexBgImage" />
  <!-- La cabecera es fija y siempre visible: el contenido le deja hueco
       arriba. La barra derecha contextual es FIJA (fuera del flujo), de
       justo bajo la cabecera hasta abajo: cada vista registra sus filtros
       con useAppRightSidebar() y Teleport a #app-right-sidebar-target; se
       abre/cierra SOLO con el asa anclada a la propia barra. Con
       `overlay-always` es drawer SUPERPUESTO en todas las anchuras (telón
       incluido): nunca le roba ancho al contenido. -->
  <div v-if="!bare" class="site-main">
    <div class="site-content">
      <RouterView />
    </div>
    <AppRightSidebar
      overlay-always
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
  <!-- Toasts del motor (mismo patrón que el admin): los usan la colección
       "para imprimir" y quien los necesite vía useToast(). -->
  <ToastContainer />
</template>
