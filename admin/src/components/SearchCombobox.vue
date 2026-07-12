<script setup lang="ts" generic="T extends ComboOption">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { Check, ChevronDown } from '@lucide/vue'

// Combobox de selección única con búsqueda en cliente y opción "rica" por
// slot (el SearchSelect del motor solo pinta texto plano, sin slots).
// Teclado (flechas + Enter + Escape) y cierre por mousedown exterior calcados
// del SearchSelect. Genérico: cada opción puede llevar datos extra que el
// slot #option recibe tipados.
export interface ComboOption {
  id: number | string
  /** Texto del trigger cerrado y fallback de la opción. */
  label: string
  /** Texto sobre el que filtra la búsqueda (si difiere del label). */
  search?: string
}

const props = withDefaults(
  defineProps<{
    modelValue?: number | string | null
    options: T[]
    label?: string
    placeholder?: string
    searchPlaceholder?: string
    noResults?: string
    error?: string
  }>(),
  {
    modelValue: null,
    placeholder: 'Elige…',
    searchPlaceholder: 'Buscar…',
    noResults: 'Sin resultados.',
  },
)

const emit = defineEmits<{ 'update:modelValue': [id: T['id']] }>()

defineSlots<{
  /** Contenido de cada opción del desplegable (fallback: label plano). */
  option?: (slotProps: { option: T }) => unknown
}>()

const open = ref(false)
const query = ref('')
const highlighted = ref(0)
const root = ref<HTMLElement | null>(null)
const input = ref<HTMLInputElement | null>(null)

const selectedLabel = computed(
  () => props.options.find((o) => o.id === props.modelValue)?.label ?? '',
)

// Filtrado en cliente sobre `search ?? label`.
const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return props.options
  return props.options.filter((o) => (o.search ?? o.label).toLowerCase().includes(q))
})

watch(filtered, () => {
  highlighted.value = 0
})

async function toggle() {
  open.value = !open.value
  if (open.value) {
    query.value = ''
    highlighted.value = 0
    document.addEventListener('mousedown', onOutside)
    await nextTick()
    input.value?.focus()
  } else {
    close()
  }
}

function close() {
  open.value = false
  document.removeEventListener('mousedown', onOutside)
}

function select(option: T) {
  emit('update:modelValue', option.id)
  close()
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    close()
    return
  }
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (filtered.value.length) highlighted.value = (highlighted.value + 1) % filtered.value.length
    return
  }
  if (e.key === 'ArrowUp') {
    e.preventDefault()
    if (filtered.value.length) {
      highlighted.value = (highlighted.value - 1 + filtered.value.length) % filtered.value.length
    }
    return
  }
  if (e.key === 'Enter') {
    e.preventDefault()
    const option = filtered.value[highlighted.value]
    if (option) select(option)
  }
}

function onOutside(e: MouseEvent) {
  if (root.value && !root.value.contains(e.target as Node)) close()
}

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onOutside)
})
</script>

<template>
  <div
    ref="root"
    class="search-combobox"
    :class="{ 'is-open': open, 'search-combobox--error': !!error }"
  >
    <span v-if="label" class="search-combobox__label">{{ label }}</span>

    <button type="button" class="search-combobox__trigger" @click="toggle">
      <span class="search-combobox__value" :class="{ 'is-placeholder': !selectedLabel }">
        {{ selectedLabel || placeholder }}
      </span>
      <ChevronDown :size="16" class="search-combobox__chevron" />
    </button>

    <div v-if="open" class="search-combobox__dropdown">
      <input
        ref="input"
        v-model="query"
        type="text"
        class="search-combobox__input"
        :placeholder="searchPlaceholder"
        @keydown="onKeydown"
      />
      <ul class="search-combobox__options">
        <li v-for="(option, index) in filtered" :key="option.id">
          <button
            type="button"
            class="search-combobox__option"
            :class="{
              'is-active': option.id === modelValue,
              'is-highlighted': index === highlighted,
            }"
            @mousedown.prevent="select(option)"
            @mouseenter="highlighted = index"
          >
            <span class="search-combobox__body">
              <slot name="option" :option="option">{{ option.label }}</slot>
            </span>
            <Check v-if="option.id === modelValue" :size="14" />
          </button>
        </li>
        <li v-if="!filtered.length" class="search-combobox__empty">{{ noResults }}</li>
      </ul>
    </div>

    <p v-if="error" class="search-combobox__error">{{ error }}</p>
  </div>
</template>
