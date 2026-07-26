<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { X } from '@lucide/vue'
import { EditModal, useToast } from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'
import SearchCombobox from '@/components/SearchCombobox.vue'
import type { DeckHeroItem, FactionDeck, Translations } from '@juego/shared'

// Modal de edición de los héroes del mazo: estado LOCAL copiado al abrir
// (nada se persiste hasta Guardar), combobox con búsqueda para añadir
// (opciones de /admin/heroes/options acotadas a las facciones del mazo) y
// filas al estilo ability-row solo con quitar (sin copias: un héroe
// asignado es siempre 1). Guardar hace el PUT de updateHeroes y emite `saved`.
const props = defineProps<{
  modelValue: boolean
  deck: FactionDeck | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const locales = useLocalesStore()

const saving = ref(false)
const items = ref<DeckHeroItem[]>([])

/** Forma del endpoint /admin/heroes/options (con facción). */
interface HeroOption {
  id: number
  name: Translations
  slug: Translations
  faction_id: number | null
}
const options = ref<HeroOption[]>([])

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

/** ¿El héroe pertenece a una facción que ya no está en el mazo? */
function isForeign(item: { faction_id: number | null }): boolean {
  return item.faction_id != null && !deckFactionIds.value.has(item.faction_id)
}

// Al abrir: copia local de los héroes del mazo + opciones del selector.
watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    items.value = (props.deck?.heroes ?? []).map((h) => ({ ...h }))
    try {
      const { data } = await api.get('/admin/heroes/options')
      options.value = data.data
    } catch {
      toast.danger(t('common.errors.load'))
    }
  },
)

// Opciones del combobox: acotadas a las facciones del mazo y sin los ya
// añadidos; la búsqueda cubre el nombre en el locale activo.
const availableHeroes = computed(() =>
  options.value
    .filter(
      (o) =>
        (o.faction_id == null || deckFactionIds.value.has(o.faction_id)) &&
        !items.value.some((h) => h.id === o.id),
    )
    .map((o) => ({ id: o.id, label: tr(o.name) })),
)

// Al elegir en el combobox se añade directamente (sin botón intermedio).
function addHero(id: number | string) {
  const option = options.value.find((o) => o.id === Number(id))
  if (!option || items.value.some((h) => h.id === option.id)) return
  items.value.push({
    id: option.id,
    name: option.name,
    image: null,
    faction_id: option.faction_id ?? null,
  })
}

function removeHero(hero: DeckHeroItem) {
  items.value = items.value.filter((h) => h.id !== hero.id)
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
    await api.put(`/admin/faction-decks/${deckSlug.value}/heroes`, {
      items: items.value.map((h) => ({ hero_id: h.id })),
    })
    toast.success(t('factionDecks.toast.heroesSaved'))
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
    :title="t('factionDecks.single.heroesTitle')"
    :loading="saving"
    :submit-label="t('common.save')"
    :cancel-label="t('common.cancel')"
    @update:model-value="(v: boolean) => emit('update:modelValue', v)"
    @submit="submit"
  >
    <div class="deck-modal">
      <!-- Combobox con búsqueda: al elegir, el héroe se añade a la lista -->
      <SearchCombobox
        :model-value="null"
        :options="availableHeroes"
        :label="t('factionDecks.single.addHeroes')"
        :placeholder="t('factionDecks.single.searchHeroes')"
        :search-placeholder="t('common.search')"
        :no-results="t('common.empty')"
        @update:model-value="addHero"
      />

      <p v-if="!items.length" class="deck-modal__hint">
        {{ t('factionDecks.single.noHeroes') }}
      </p>
      <ul v-else class="deck-modal__rows">
        <li
          v-for="hero in sortedItems"
          :key="hero.id"
          class="ability-row"
          :class="{ 'is-foreign': isForeign(hero) }"
        >
          <span class="ability-row__name" :title="tr(hero.name)">{{ tr(hero.name) }}</span>
          <!-- Aviso de facción fuera del mazo, en el hueco de los metadatos -->
          <span v-if="isForeign(hero)" class="ability-row__meta deck-modal__foreign-note">
            {{ t('factionDecks.single.notInFactions') }}
          </span>
          <span class="ability-row__actions deck-modal__actions">
            <button
              type="button"
              class="deck-modal__remove"
              :aria-label="t('factionDecks.single.removeHero')"
              :title="t('factionDecks.single.removeHero')"
              @click="removeHero(hero)"
            >
              <X :size="14" />
            </button>
          </span>
        </li>
      </ul>
    </div>
  </EditModal>
</template>
