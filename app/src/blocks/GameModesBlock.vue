<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { BlockShell } from '@edc-motor/ui'
import { useLocalesStore } from '@/stores/locales'
import { sectionIndexRoute } from '@/entities/singleRoutes'

// Bloque con-datos del juego (game-modes del viejo): todos los modos con su
// configuración de mazos y el nº de mazos publicados, enlazando al índice.
interface ModeItem {
  id: number
  name: string
  description: string | null
  config: {
    min_cards: number
    max_cards: number
    max_copies_per_card: number
    required_heroes: number
  } | null
  decks_count: number
}

const props = defineProps<{
  settings: Record<string, unknown>
  data: { modes?: ModeItem[] }
}>()

const { t } = useI18n()
const locales = useLocalesStore()

const modes = computed(() => props.data.modes ?? [])
const decksRoute = computed(() => sectionIndexRoute('decks', locales.current))
</script>

<!-- eslint-disable vue/no-v-html -- HTML del wysiwyg, saneado en servidor (DC-09) -->
<template>
  <BlockShell :settings="settings" class="block--game-modes">
    <h2 v-if="settings.title" class="block__title">{{ settings.title }}</h2>
    <p v-if="settings.subtitle" class="block__subtitle">{{ settings.subtitle }}</p>
    <div v-if="settings.intro" class="block__text rich-content" v-html="settings.intro" />

    <div v-if="modes.length" class="game-modes-list">
      <article v-for="mode in modes" :key="mode.id" class="game-modes-list__item">
        <h3 class="game-modes-list__name">{{ mode.name }}</h3>
        <div
          v-if="mode.description"
          class="game-modes-list__description rich-content"
          v-html="mode.description"
        />

        <dl v-if="mode.config" class="game-modes-list__config">
          <div>
            <dt>{{ t('blocks.gameModes.cards') }}</dt>
            <dd>{{ mode.config.min_cards }}–{{ mode.config.max_cards }}</dd>
          </div>
          <div>
            <dt>{{ t('blocks.gameModes.maxCopies') }}</dt>
            <dd>{{ mode.config.max_copies_per_card }}</dd>
          </div>
          <div>
            <dt>{{ t('blocks.gameModes.heroes') }}</dt>
            <dd>{{ mode.config.required_heroes }}</dd>
          </div>
        </dl>

        <RouterLink v-if="decksRoute" class="game-modes-list__decks" :to="decksRoute">
          <span class="game-modes-list__count">{{ mode.decks_count }}</span>
          {{ t('blocks.gameModes.viewDecks') }}
        </RouterLink>
      </article>
    </div>
  </BlockShell>
</template>
