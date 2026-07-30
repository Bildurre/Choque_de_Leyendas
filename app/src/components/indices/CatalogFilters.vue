<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ChevronDown, Funnel } from '@lucide/vue'
import { useAppRightSidebar, type AppRightSidebarToken } from '@edc-motor/ui'

// Colocación responsive de los filtros de un catálogo de índice:
// - En ANCHO los filtros van DEBAJO de la barra de búsqueda, en un panel
//   en línea plegable (botón «Filtros» con contador de activos).
// - En ESTRECHO (cuando el nav pasa a barra lateral, <900px) se
//   teletransportan a la barra derecha off-canvas del motor
//   (AppRightSidebar), que SOLO se registra en ese tramo.
// El corte lo decide el propio motor (useAppRightSidebar().overlay, el
// mismo umbral que convierte la barra en drawer): un único interruptor
// para todos los catálogos. El contenido (MultiSelects + «Quitar
// filtros») lo pone cada catálogo por slot y NUNCA se desmonta al cruzar
// el corte (solo viaja), así que el estado de los selects se conserva.
withDefaults(
  defineProps<{
    /** Nº de filtros activos (badge del botón «Filtros»). */
    activeCount?: number
  }>(),
  { activeCount: 0 },
)

const { t } = useI18n()
const sidebar = useAppRightSidebar()
const { overlay } = sidebar

// Panel en línea plegado por defecto (los filtros pueden ocupar mucho).
const open = ref(false)

// La barra derecha SOLO existe en estrecho: se registra al entrar en el
// tramo drawer y se des-registra al salir (o al desmontar la vista).
let token: AppRightSidebarToken | undefined
watch(
  overlay,
  (isDrawer) => {
    if (isDrawer) token = sidebar.register('', token)
    else if (token) sidebar.unregister(token)
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  if (token) sidebar.unregister(token)
})
</script>

<template>
  <div class="catalog-filters">
    <button
      v-if="!overlay"
      type="button"
      class="catalog-filters__toggle"
      :aria-expanded="open"
      @click="open = !open"
    >
      <Funnel :size="16" />
      <span>{{ t('nav.filters') }}</span>
      <span v-if="activeCount > 0" class="catalog-filters__count">{{ activeCount }}</span>
      <ChevronDown :size="16" class="catalog-filters__chevron" :class="{ 'is-open': open }" />
    </button>

    <Teleport :disabled="!overlay" defer to="#app-right-sidebar-target">
      <div
        v-show="overlay || open"
        class="catalog-filters__panel"
        :class="{ 'catalog-filters__panel--inline': !overlay }"
      >
        <slot />
      </div>
    </Teleport>
  </div>
</template>
