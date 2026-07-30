<script setup lang="ts">
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import IndexHeader from '@/components/IndexHeader.vue'
import HeroesCatalog from '@/components/indices/HeroesCatalog.vue'
import { useIndexPage } from '@/entities/indexPage'

// Índice público de héroes: CÁSCARA (canónica del locale DC-12 + SEO +
// IndexHeader). El interior — búsqueda, filtros de la barra derecha y
// rejilla con paginación doble — vive en HeroesCatalog, compartido con el
// bloque «Índice de entidad» del CRM.
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
  <main v-if="section" class="catalog-index heroes-index">
    <IndexHeader :title="t(section.titleKey)" :subtitle="t('entitiesIntro.heroes')" />
    <HeroesCatalog :section="section" />
  </main>
</template>
