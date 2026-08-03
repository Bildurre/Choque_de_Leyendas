<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useLocalesStore } from '@/stores/locales'
import { DOWNLOAD_PATHS } from '@/router/downloads'
import CollectionManager from '@/components/CollectionManager.vue'

// Sección del panel de ESTE juego (doc 10 + doc 02): la misma colección
// "para imprimir" que la página pública de Descargas (componente
// compartido); las cartas se añaden desde los listados públicos.
const { t } = useI18n()
const locales = useLocalesStore()
</script>

<template>
  <div class="print-panel">
    <h2>{{ t('account.sections.print') }}</h2>
    <!-- Solo el enlace a la página del CRM de Descargas (bloque
         «Descargas») por su slug: el gestor ya explica qué hacer. -->
    <p class="print-panel__intro">
      <RouterLink
        :to="{
          name: 'page',
          params: { locale: locales.current, slug: DOWNLOAD_PATHS[locales.current] ?? 'descargas' },
        }"
        >{{ t('nav.downloads') }}</RouterLink
      >
    </p>
    <CollectionManager />
  </div>
</template>
