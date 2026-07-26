<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { X } from '@lucide/vue'
import { EditModal, NumberInput, useToast } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'
import SearchCombobox from '@/components/SearchCombobox.vue'
import CostDice from '@/components/game/CostDice.vue'
import type { DeckCardItem, FactionDeck, Translations } from '@juego/shared'

// Modal de edición de las cartas del mazo: estado LOCAL copiado al abrir
// (nada se persiste hasta Guardar), combobox con búsqueda para añadir
// (opciones de /admin/cards/options acotadas a las facciones del mazo) y
// filas al estilo ability-row con las copias en NumberInput (mín. 1, sin
// máximo: el borrador es libre; `invalid` marca las que superan el límite
// del modo). Guardar hace el PUT de updateCards y emite `saved`.
const props = defineProps<{
  modelValue: boolean
  deck: FactionDeck | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()

const saving = ref(false)
const items = ref<DeckCardItem[]>([])

/** Forma del endpoint /admin/cards/options (con facción y coste). */
interface CardOption {
  id: number
  name: Translations
  slug: Translations
  faction_id: number | null
  cost: string | null
}
const options = ref<CardOption[]>([])

function tr(obj: Translations | null | undefined): string {
  return (
    obj?.[locales.current] || obj?.[locales.defaultLocale] || Object.values(obj || {})[0] || '—'
  )
}

// Slug del mazo en el locale activo (los PUT resuelven por slug).
const deckSlug = computed(() => {
  const slug = props.deck?.slug
  return slug?.[locales.current] || Object.values(slug ?? {})[0] || ''
})

// Facciones del mazo: acotan los disponibles y marcan lo que sobra.
const deckFactionIds = computed(() => new Set((props.deck?.factions ?? []).map((f) => f.id)))

// Límites del modo (embebidos en el show): solo avisan, no bloquean.
const config = computed(() => props.deck?.game_mode ?? null)

/** ¿La carta pertenece a una facción que ya no está en el mazo? */
function isForeign(item: { faction_id: number | null }): boolean {
  return item.faction_id != null && !deckFactionIds.value.has(item.faction_id)
}

// Al abrir: copia local de las cartas del mazo + opciones del selector.
watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    items.value = (props.deck?.cards ?? []).map((c) => ({ ...c }))
    try {
      const { data } = await api.get('/admin/cards/options')
      options.value = data.data
    } catch {
      toast.danger(t('common.errors.load'))
    }
  },
)

// Opciones del combobox: acotadas a las facciones del mazo y sin las ya
// añadidas; la búsqueda cubre el nombre en el locale activo.
const availableCards = computed(() =>
  options.value
    .filter(
      (o) =>
        (o.faction_id == null || deckFactionIds.value.has(o.faction_id)) &&
        !items.value.some((c) => c.id === o.id),
    )
    .map((o) => ({ id: o.id, label: tr(o.name), card: o })),
)

// Al elegir en el combobox se añade directamente (sin botón intermedio).
function addCard(id: number | string) {
  const option = options.value.find((o) => o.id === Number(id))
  if (!option || items.value.some((c) => c.id === option.id)) return
  items.value.push({
    id: option.id,
    name: option.name,
    cost: option.cost ?? null,
    image: null,
    faction_id: option.faction_id ?? null,
    copies: 1,
  })
}

function removeCard(card: DeckCardItem) {
  items.value = items.value.filter((c) => c.id !== card.id)
}

// Sin numerales de orden (aquí el orden no significa nada): alfabético por
// el nombre del locale activo, como el resto de listas.
const sortedItems = computed(() =>
  [...items.value].sort((a, b) => tr(a.name).localeCompare(tr(b.name), locales.current)),
)

async function submit() {
  if (!props.deck) return
  saving.value = true
  try {
    await api.put(`/admin/faction-decks/${deckSlug.value}/cards`, {
      items: items.value.map((c) => ({ card_id: c.id, copies: c.copies })),
    })
    toast.success(t('factionDecks.toast.cardsSaved'))
    emit('saved')
    emit('update:modelValue', false)
  } catch {
    toast.danger(t('factionDecks.toast.saveError'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <EditModal
    :model-value="modelValue"
    :title="t('factionDecks.single.cardsTitle')"
    :loading="saving"
    :submit-label="t('common.save')"
    :cancel-label="t('common.cancel')"
    @update:model-value="(v: boolean) => emit('update:modelValue', v)"
    @submit="submit"
  >
    <div class="deck-modal">
      <!-- Combobox con búsqueda: al elegir, la carta se añade a la lista -->
      <SearchCombobox
        :model-value="null"
        :options="availableCards"
        :label="t('factionDecks.single.addCards')"
        :placeholder="t('factionDecks.single.searchCards')"
        :search-placeholder="t('common.search')"
        :no-results="t('common.empty')"
        @update:model-value="addCard"
      >
        <template #option="{ option }">
          <span class="ability-option">
            <span class="ability-option__name">{{ option.label }}</span>
            <CostDice
              v-if="option.card.cost"
              class="ability-option__cost"
              :cost="option.card.cost"
            />
          </span>
        </template>
      </SearchCombobox>

      <p v-if="!items.length" class="deck-modal__hint">
        {{ t('factionDecks.single.noCards') }}
      </p>
      <ul v-else class="deck-modal__rows">
        <li
          v-for="card in sortedItems"
          :key="card.id"
          class="ability-row"
          :class="{ 'is-foreign': isForeign(card) }"
        >
          <span class="ability-row__name" :title="tr(card.name)">{{ tr(card.name) }}</span>
          <!-- Aviso de facción fuera del mazo, en el hueco de los metadatos -->
          <span v-if="isForeign(card)" class="ability-row__meta deck-modal__foreign-note">
            {{ t('factionDecks.single.notInFactions') }}
          </span>
          <CostDice v-if="card.cost" class="ability-row__cost" :cost="card.cost" />
          <span class="ability-row__actions deck-modal__actions">
            <!-- Copias libres en borrador: sin máximo, `invalid` si se pasa -->
            <NumberInput
              :model-value="card.copies"
              :min="1"
              :invalid="!!config && card.copies > config.max_copies_per_card"
              :label="t('factionDecks.single.copies')"
              :decrease-label="t('factionDecks.single.fewerCopies')"
              :increase-label="t('factionDecks.single.moreCopies')"
              @update:model-value="card.copies = $event"
            />
            <button
              type="button"
              class="deck-modal__remove"
              :aria-label="t('factionDecks.single.removeCard')"
              :title="t('factionDecks.single.removeCard')"
              @click="removeCard(card)"
            >
              <X :size="14" />
            </button>
          </span>
        </li>
      </ul>
    </div>
  </EditModal>
</template>
