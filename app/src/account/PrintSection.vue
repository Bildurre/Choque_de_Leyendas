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
    <p class="print-panel__intro">
      {{ t('collection.intro') }}
      <RouterLink
        :to="{
          name: 'downloads',
          params: { locale: locales.current, dl: DOWNLOAD_PATHS[locales.current] ?? 'descargas' },
        }"
        >{{ t('nav.downloads') }}</RouterLink
      >
    </p>
    <CollectionManager />
  </div>
</template>
