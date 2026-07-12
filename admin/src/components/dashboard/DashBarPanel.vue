<script setup lang="ts">
// Panel de barras horizontales en CSS puro para el dashboard. Cada fila
// puede llevar su color (--c, p. ej. el de la facción) y un sufijo (rango).
export interface BarRow {
  key: string | number
  label: string
  count: number | string
  color?: string
  suffix?: string
}

const props = defineProps<{
  title: string
  rows: BarRow[]
  max: number
}>()

function width(count: number | string): string {
  return `${(Number(count) / Math.max(props.max, 1)) * 100}%`
}
</script>

<template>
  <article class="dash-panel">
    <h3 class="dash-panel__title">{{ title }}</h3>
    <div class="dash-bars">
      <div v-for="row in rows" :key="row.key" class="dash-bars__row">
        <span class="dash-bars__label" :title="row.label">{{ row.label }}</span>
        <span class="dash-bars__track">
          <span
            class="dash-bars__fill"
            :style="{ width: width(row.count), '--c': row.color }"
          ></span>
        </span>
        <span class="dash-bars__count">
          {{ row.count }}
          <small v-if="row.suffix" class="dash-bars__range">{{ row.suffix }}</small>
        </span>
      </div>
    </div>
  </article>
</template>
