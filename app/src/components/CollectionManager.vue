<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Download, FileText, Trash2, X } from '@lucide/vue'
import { useI18n } from 'vue-i18n'
import { BaseButton } from '@edc-motor/ui'
import { useCollectionStore, type CollectionItem } from '@/stores/collection'
import { useLocalesStore } from '@/stores/locales'

// La colección "para imprimir" (doc 02): lista con copias, vaciar, generar
// el PDF temporal y descargarlo. La usan la página de Descargas y la
// sección del panel de usuario; funciona igual para invitados (token).
const { t } = useI18n()
const locales = useLocalesStore()
const collection = useCollectionStore()
const error = ref<string | null>(null)

async function setCopies(item: CollectionItem, copies: number) {
  if (copies < 1 || copies > 99) return
  error.value = null
  try {
    await collection.add(item.entity, item.entity_id, copies)
  } catch {
    error.value = t('collection.error')
  }
}

async function remove(item: CollectionItem) {
  error.value = null
  try {
    await collection.remove(item)
  } catch {
    error.value = t('collection.error')
  }
}

async function clear() {
  error.value = null
  try {
    await collection.clear()
  } catch {
    error.value = t('collection.error')
  }
}

async function generate() {
  error.value = null
  try {
    await collection.generate(locales.current)
    if (collection.pdf?.status === 'failed') error.value = t('collection.failed')
  } catch {
    error.value = t('collection.failed')
  }
}

onMounted(() => {
  if (!collection.loaded) collection.load()
})
</script>

<template>
  <div class="print-section">
    <p v-if="!collection.items.length" class="print-section__empty">
      {{ t('collection.empty') }}
    </p>
    <ul v-else class="print-section__items">
      <li v-for="item in collection.items" :key="item.id" class="print-section__item">
        <img v-if="item.preview" class="print-section__thumb" :src="item.preview" alt="" />
        <span class="print-section__label">{{ item.label ?? '—' }}</span>
        <span class="print-section__copies">
          <input
            :value="item.copies"
            type="number"
            min="1"
            max="99"
            @change="(e) => setCopies(item, Number((e.target as HTMLInputElement).value))"
          />
          × {{ t('collection.copies') }}
        </span>
        <button
          class="print-section__remove"
          type="button"
          :title="t('collection.remove')"
          @click="remove(item)"
        >
          <X :size="16" />
        </button>
      </li>
    </ul>

    <div v-if="collection.items.length" class="print-section__actions">
      <BaseButton :disabled="collection.generating" @click="generate">
        <template #icon><FileText :size="16" /></template>
        {{ collection.generating ? t('collection.generating') : t('collection.generate') }}
      </BaseButton>
      <BaseButton variant="secondary" @click="clear">
        <template #icon><Trash2 :size="16" /></template>
        {{ t('collection.clear') }}
      </BaseButton>
      <a
        v-if="collection.pdf?.status === 'ready' && collection.pdf.url"
        class="print-section__download"
        :href="collection.pdf.url"
        target="_blank"
        rel="noopener"
      >
        <Download :size="16" /> {{ t('collection.download') }}
      </a>
    </div>
    <p v-if="error" class="error">{{ error }}</p>
  </div>
</template>
