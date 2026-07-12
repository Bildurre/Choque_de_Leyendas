<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { BaseSelect } from '@edc-motor/ui'

// Select compacto de ordenación de listados (contrato compartido con la API):
// '' = recientes (id desc), 'name' = nombre A-Z, 'name_desc' = nombre Z-A.
const model = defineModel<string>({ default: '' })
const { t } = useI18n()

const options = computed(() => [
  { value: '', label: t('common.sort.latest') },
  { value: 'name', label: t('common.sort.nameAsc') },
  { value: 'name_desc', label: t('common.sort.nameDesc') },
])
</script>

<template>
  <BaseSelect v-model="model" :options="options" class="sort-select" />
</template>
