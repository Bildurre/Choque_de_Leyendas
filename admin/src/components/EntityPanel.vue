<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import {
  ArrowRight,
  Camera,
  Eye,
  EyeOff,
  FlameKindling,
  RotateCcw,
  SquarePen,
  Trash2,
} from '@lucide/vue'
import { BaseButton } from '@edc-motor/ui'
import { useLocalesStore } from '@/stores/locales'
import type { EntityBase } from '@juego/shared'

// Panel derecho de los listados de entidades (patrón kontuan): TODAS las
// acciones arriba del todo (la tarjeta solo lleva las básicas) + info del
// elemento (estado, previews por idioma). Las acciones las ejecuta la vista
// (vienen de useEntityList); aquí solo se emiten.
defineProps<{
  /** Elemento seleccionado (null => mensaje de "selecciona"). */
  item: EntityBase | null
  /** Nombre ya traducido del elemento (tr(nameOf(item))). */
  name: string
  /** Kicker del panel (p. ej. "Casa"). */
  kicker: string
  /** Texto cuando no hay selección. */
  empty: string
  /** La entidad se renderiza a PNG: muestra regenerar + imágenes por idioma. */
  hasPreview?: boolean
}>()

defineEmits<{
  open: []
  edit: []
  togglePublish: []
  regenerate: []
  del: []
  restore: []
  forceDelete: []
}>()

defineSlots<{
  /** Info extra específica de la entidad (color, stats…). */
  meta?: () => unknown
}>()

const { t } = useI18n()
const locales = useLocalesStore()
</script>

<template>
  <Teleport defer to="#right-sidebar-target">
    <div class="manager-panel">
      <p v-if="!item" class="manager-panel__empty">{{ empty }}</p>
      <template v-else>
        <p class="manager-panel__kicker">{{ kicker }}</p>

        <!-- Acciones PRIMERO; después, secciones separadas (patrón panel) -->
        <div class="manager-detail__actions">
          <template v-if="item.deleted_at">
            <BaseButton variant="success" @click="$emit('restore')">
              <template #icon><RotateCcw :size="14" /></template>
              {{ t('common.actions.restore') }}
            </BaseButton>
            <BaseButton variant="danger" @click="$emit('forceDelete')">
              <template #icon><FlameKindling :size="14" /></template>
              {{ t('common.actions.forceDelete') }}
            </BaseButton>
          </template>
          <template v-else>
            <BaseButton @click="$emit('open')">
              <template #icon><ArrowRight :size="14" /></template>
              {{ t('common.actions.open') }}
            </BaseButton>
            <BaseButton variant="info" @click="$emit('edit')">
              <template #icon><SquarePen :size="14" /></template>
              {{ t('common.actions.edit') }}
            </BaseButton>
            <BaseButton variant="warning" @click="$emit('togglePublish')">
              <template #icon>
                <component :is="item.is_published ? EyeOff : Eye" :size="14" />
              </template>
              {{ item.is_published ? t('common.actions.unpublish') : t('common.actions.publish') }}
            </BaseButton>
            <BaseButton v-if="hasPreview" variant="success" @click="$emit('regenerate')">
              <template #icon><Camera :size="14" /></template>
              {{ t('previews.regenerate') }}
            </BaseButton>
            <BaseButton variant="danger" @click="$emit('del')">
              <template #icon><Trash2 :size="14" /></template>
              {{ t('common.actions.delete') }}
            </BaseButton>
          </template>
        </div>

        <hr class="manager-panel__divider" />

        <h3 class="manager-detail__title">{{ name }}</h3>
        <slot name="meta" />

        <!-- PNG por idioma (solo entidades renderizables) -->
        <hr v-if="hasPreview" class="manager-panel__divider" />
        <div v-if="hasPreview" class="manager-detail__figures">
          <figure
            v-for="locale in locales.locales"
            :key="locale.code"
            class="manager-detail__figure"
            :class="{ 'is-missing': !item.previews?.[locale.code] }"
          >
            <img
              v-if="item.previews?.[locale.code]"
              :src="item.previews?.[locale.code]"
              :alt="locale.code"
            />
            <span v-else class="manager-detail__hole">—</span>
            <figcaption>{{ locale.code.toUpperCase() }}</figcaption>
          </figure>
        </div>
      </template>
    </div>
  </Teleport>
</template>
