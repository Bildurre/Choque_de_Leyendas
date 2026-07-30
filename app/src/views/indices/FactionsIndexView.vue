<script setup lang="ts">
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import IndexHeader from '@/components/IndexHeader.vue'
import FactionsCatalog from '@/components/indices/FactionsCatalog.vue'
import { useIndexPage } from '@/entities/indexPage'

// Índice público de facciones: CÁSCARA (canónica del locale DC-12 + SEO +
// IndexHeader). El interior — búsqueda y rejilla de tarjetas CSS — vive en
// FactionsCatalog, compartido con el bloque «Índice de entidad» del CRM.
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
  <main v-if="section" class="factions-index">
    <IndexHeader :title="t(section.titleKey)" :subtitle="t('entitiesIntro.factions')" />
    <FactionsCatalog :section="section" />
  </main>
</template>
