<script setup lang="ts">
import { onMounted } from 'vue'
import { Download, Eye, FileText, Trash2 } from '@lucide/vue'
import { useI18n } from 'vue-i18n'
import { BaseButton, NumericInput, useToast } from '@edc-motor/ui'
import { formatDate, formatSize, inlineUrl } from '@/lib/format'
import { useCollectionStore, type CollectionItem } from '@/stores/collection'
import { useLocalesStore } from '@/stores/locales'

// La colección "para imprimir" (doc 02), en dos secciones: ARRIBA los PDF
// temporales ya generados y aún vigentes (persisten tras recargar: los da
// la clave `generated` del índice del motor) con la barra «generando»
// mientras dura la cola; DEBAJO las cartas/héroes añadidos (grid de cards
// con copias y quitar) con Generar/Vaciar encima. La usan la pestaña
// «Mi colección» de Descargas y la sección del panel de usuario; funciona
// igual para invitados (token).
const { t } = useI18n()
const locales = useLocalesStore()
const collection = useCollectionStore()
const toast = useToast()

async function setCopies(item: CollectionItem, copies: number) {
  if (copies < 1 || copies > 99) return
  try {
    await collection.add(item.entity, item.entity_id, copies)
  } catch {
    toast.danger(t('collection.error'))
  }
}

async function remove(item: CollectionItem) {
  try {
    await collection.remove(item)
  } catch {
    toast.danger(t('collection.error'))
  }
}

async function clear() {
  try {
    await collection.clear()
  } catch {
    toast.danger(t('collection.error'))
  }
}

async function generate() {
  const status = await collection.generate(locales.current)
  if (status === 'ready') toast.success(t('collection.readyToast'))
  else toast.danger(t('collection.failed'))
}

onMounted(() => {
  if (!collection.loaded) collection.load()
})
</script>

<template>
  <div class="collection-manager">
    <!-- Sección superior: los PDF ya generados, disponibles para descargar
         (cards con el MISMO lenguaje que las descargas públicas: clases
         downloads__item y compañía de _downloads.scss). -->
    <section
      v-if="collection.readyPdfs.length || collection.generating"
      class="collection-manager__section"
    >
      <h3>{{ t('collection.generated') }}</h3>

      <!-- Barra «generando» indeterminada: dura lo que dure la cola. -->
      <div
        v-if="collection.generating"
        class="collection-manager__progress"
        role="status"
        :aria-label="t('collection.generating')"
      >
        <span class="collection-manager__progress-label">{{ t('collection.generating') }}</span>
        <span class="collection-manager__progress-track" aria-hidden="true">
          <span class="collection-manager__progress-fill" />
        </span>
      </div>

      <ul v-if="collection.readyPdfs.length" class="collection-manager__pdfs">
        <li v-for="pdf in collection.readyPdfs" :key="pdf.id" class="downloads__item">
          <div class="downloads__header">
            <h4 class="downloads__name">
              <span class="chip downloads__chip">{{ pdf.locale.toUpperCase() }}</span>
              <span class="downloads__title">{{ pdf.filename }}</span>
            </h4>
            <p class="downloads__meta">
              <span v-if="pdf.size">{{ formatSize(pdf.size) }}</span>
              <span v-if="pdf.size && pdf.generated_at" class="downloads__sep" aria-hidden="true">
                •
              </span>
              <span v-if="pdf.generated_at">{{
                formatDate(pdf.generated_at, locales.current)
              }}</span>
            </p>
          </div>
          <div v-if="pdf.url" class="downloads__actions">
            <a
              class="downloads__link"
              :href="inlineUrl(pdf.url)"
              target="_blank"
              rel="noopener"
              :title="t('downloads.view')"
              :aria-label="t('downloads.view')"
            >
              <Eye :size="18" />
            </a>
            <a
              class="downloads__link"
              :href="pdf.url"
              download
              :title="t('downloads.download')"
              :aria-label="t('downloads.download')"
            >
              <Download :size="18" />
            </a>
          </div>
        </li>
      </ul>
    </section>

    <!-- Sección inferior: las cartas/héroes añadidos y la generación. -->
    <section class="collection-manager__section">
      <h3>{{ t('collection.items') }}</h3>

      <p v-if="!collection.items.length" class="collection-manager__empty">
        {{ collection.generating ? t('collection.emptyGenerating') : t('collection.empty') }}
      </p>

      <template v-else>
        <!-- Generar y Vaciar ARRIBA, encima del grid. -->
        <div class="collection-manager__actions">
          <BaseButton :disabled="collection.generating" @click="generate">
            <template #icon><FileText :size="16" /></template>
            {{ t('collection.generate') }}
          </BaseButton>
          <BaseButton variant="secondary" :disabled="collection.generating" @click="clear">
            <template #icon><Trash2 :size="16" /></template>
            {{ t('collection.clear') }}
          </BaseButton>
        </div>

        <ul class="collection-manager__grid">
          <li v-for="item in collection.items" :key="item.id" class="collection-manager__card">
            <img v-if="item.preview" class="collection-manager__thumb" :src="item.preview" alt="" />
            <!-- Fila superior: nombre + quitar (el cubo va ARRIBA) -->
            <div class="collection-manager__head">
              <span class="collection-manager__label" :title="item.label ?? undefined">
                {{ item.label ?? '—' }}
              </span>
              <button
                class="collection-manager__remove"
                type="button"
                :title="t('collection.remove')"
                :aria-label="t('collection.remove')"
                @click="remove(item)"
              >
                <Trash2 :size="18" />
              </button>
            </div>
            <div class="collection-manager__controls">
              <NumericInput
                :model-value="item.copies"
                :min="1"
                :max="99"
                @update:model-value="(copies) => setCopies(item, copies)"
              />
            </div>
          </li>
        </ul>
      </template>
    </section>
  </div>
</template>
