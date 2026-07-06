<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ImageOff, ImagePlus, RefreshCw } from '@lucide/vue'
import { BaseButton, useToast } from '@edc-motor/ui'
import { api } from '@/lib/api'

// PNG generados de una entidad renderizable (Fase 3): los muestra por locale
// y permite regenerarlos en cola con un clic.
const props = defineProps<{ entity: string; id: number }>()

const { t } = useI18n()
const toast = useToast()

const urls = ref<Record<string, string>>({})
const loading = ref(true)
const regenerating = ref(false)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/admin/previews/${props.entity}/${props.id}`)
    urls.value = data.data
  } catch {
    toast.danger(t('common.errors.load'))
  } finally {
    loading.value = false
  }
}

async function regenerate() {
  regenerating.value = true
  try {
    await api.post(`/admin/previews/${props.entity}/${props.id}/regenerate`)
    toast.success(t('previews.queued'))
  } catch {
    toast.danger(t('common.errors.action'))
  } finally {
    regenerating.value = false
  }
}

onMounted(load)
defineExpose({ load })
</script>

<template>
  <section class="preview-panel">
    <div class="preview-panel__bar">
      <h2>{{ t('previews.title') }}</h2>
      <div class="preview-panel__actions">
        <BaseButton variant="secondary" :disabled="loading" @click="load">
          <template #icon><RefreshCw :size="16" /></template>
          {{ t('previews.refresh') }}
        </BaseButton>
        <BaseButton :disabled="regenerating" @click="regenerate">
          <template #icon><ImagePlus :size="16" /></template>
          {{ t('previews.regenerate') }}
        </BaseButton>
      </div>
    </div>

    <p v-if="!loading && !Object.keys(urls).length" class="preview-panel__empty">
      <ImageOff :size="16" /> {{ t('previews.empty') }}
    </p>

    <div v-else class="preview-panel__grid">
      <figure v-for="(url, locale) in urls" :key="locale" class="preview-panel__item">
        <img :src="url" :alt="`${entity} ${locale}`" />
        <figcaption>{{ String(locale).toUpperCase() }}</figcaption>
      </figure>
    </div>
  </section>
</template>
