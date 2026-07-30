<script setup lang="ts">
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import IndexHeader from '@/components/IndexHeader.vue'
import FactionDecksCatalog from '@/components/indices/FactionDecksCatalog.vue'
import { useIndexPage } from '@/entities/indexPage'

// Índice público de mazos: CÁSCARA (canónica del locale DC-12 + SEO +
// IndexHeader). El interior — búsqueda, pestañas por modo, filtro de
// facción y rejilla de tarjetas — vive en FactionDecksCatalog, compartido
// con el bloque «Índice de entidad» del CRM.
const { t } = useI18n()
const { locales, site, segment, section, canonicalize, applyHead } = useIndexPage()

watch(
  [segment, () => locales.current],
  async () => {
    if (!section.value || canonicalize()) return
    await site.load() // el head usa documentTitle: sin carreras en el prerender
    applyHead(t(section.value.titleKey))
  },
  { immediate: true },
)
</script>

<template>
  <main v-if="section" class="decks-index">
    <IndexHeader :title="t(section.titleKey)" :subtitle="t('entitiesIntro.decks')" />
    <FactionDecksCatalog :section="section" />
  </main>
</template>
