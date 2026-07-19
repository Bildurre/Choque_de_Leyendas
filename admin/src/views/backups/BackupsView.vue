<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Download, FileUp, Plus, RotateCcw, Save, Trash2 } from '@lucide/vue'
import {
  BaseButton,
  BaseCheckbox,
  BaseInput,
  BaseSelect,
  NumericInput,
  useConfirm,
  useToast,
} from '@edc-motor/ui'
import { useRightSidebar } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'

// Copias de seguridad (doc 06): crear (EN COLA: la petición no espera al
// zip y la vista sondea el flag `pending`), subir una copia externa,
// restaurar (destructivo: doble confirmación), listar, descargar y borrar;
// la fila entera selecciona y el panel derecho trae las acciones. La copia
// AUTOMÁTICA (frecuencia, hora, retención) se configura aquí y la programa
// el scheduler del motor. Cada copia lleva su ORIGEN (manual/auto/subida).
interface BackupRow {
  file: string
  date: string
  size: number
  origin: 'manual' | 'auto' | 'upload'
}

interface BackupSchedule {
  auto: boolean
  frequency: 'daily' | 'weekly'
  time: string
  weekday: number
  keep_days: number
}

const { t, locale } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()

const sidebar = useRightSidebar()
sidebar.useRegister(t('backups.panelTitle'))

const backups = ref<BackupRow[]>([])
const loading = ref(true)
const creating = ref(false)
const pending = ref(false)
const restoring = ref(false)
const selectedFile = ref<string | null>(null)
const schedule = ref<BackupSchedule | null>(null)
const savingSchedule = ref(false)

// Subida de una copia externa (zip de spatie/laravel-backup).
const uploadInput = ref<HTMLInputElement | null>(null)
const uploadFile = ref<File | null>(null)
const uploading = ref(false)
const UPLOAD_MAX_MB = 500 // espejo de motor.backup.upload_max_mb

const selected = computed(() => backups.value.find((b) => b.file === selectedFile.value) ?? null)

const frequencyOptions = computed(() => [
  { value: 'daily', label: t('backups.schedule.daily') },
  { value: 'weekly', label: t('backups.schedule.weekly') },
])

// 1 = lunes … 7 = domingo (como lo espera la API).
const weekdayOptions = computed(() =>
  [1, 2, 3, 4, 5, 6, 7].map((day) => ({
    value: String(day),
    label: t(`backups.schedule.weekdays.${day}`),
  })),
)

let alive = true
onBeforeUnmount(() => {
  alive = false
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/backups')
    backups.value = data.data
    schedule.value = data.schedule
    pending.value = data.pending ?? false
    // Si hay una copia en curso (p. ej. al volver a la vista), sigue el sondeo.
    if (pending.value) void poll()
  } catch {
    toast.danger(t('common.errors.load'))
  } finally {
    loading.value = false
  }
}

/** Sondea el listado mientras haya una copia en curso (sin bloquear nada). */
let polling = false
async function poll() {
  if (polling) return
  polling = true
  try {
    for (let i = 0; i < 120 && pending.value && alive; i++) {
      await new Promise((resolve) => setTimeout(resolve, 2000))
      const { data } = await api.get('/admin/backups')
      backups.value = data.data
      pending.value = data.pending ?? false
    }
    if (!pending.value && alive) toast.success(t('backups.toast.created'))
  } catch {
    // el sondeo es best-effort: el listado se refresca al volver
  } finally {
    polling = false
  }
}

async function saveSchedule() {
  if (!schedule.value) return
  savingSchedule.value = true
  try {
    const { data } = await api.put('/admin/backups/schedule', schedule.value)
    schedule.value = data.schedule
    toast.success(t('backups.schedule.saved'))
  } catch {
    toast.danger(t('common.errors.action'))
  } finally {
    savingSchedule.value = false
  }
}

function select(backup: BackupRow, event: MouseEvent) {
  const target = event.target as HTMLElement | null
  if (target?.closest('button, a')) return
  selectedFile.value = backup.file
  sidebar.reveal()
}

/** Crear va EN COLA: la respuesta vuelve al momento y el sondeo refresca. */
async function create() {
  creating.value = true
  try {
    const { data } = await api.post('/admin/backups')
    backups.value = data.data
    pending.value = data.pending ?? false
    toast.success(t('backups.toast.queued'))
    void poll()
  } catch {
    toast.danger(t('common.errors.action'))
  } finally {
    creating.value = false
  }
}

function onUploadChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  if (!file) return
  if (!file.name.toLowerCase().endsWith('.zip')) {
    toast.danger(t('common.fileType'))
    input.value = ''
    return
  }
  if (file.size > UPLOAD_MAX_MB * 1024 * 1024) {
    toast.danger(t('common.fileTooLarge'))
    input.value = ''
    return
  }
  uploadFile.value = file
}

async function upload() {
  if (!uploadFile.value) return
  uploading.value = true
  try {
    const form = new FormData()
    form.append('file', uploadFile.value)
    const { data } = await api.post('/admin/backups/upload', form)
    backups.value = data.data
    uploadFile.value = null
    if (uploadInput.value) uploadInput.value.value = ''
    toast.success(t('backups.toast.uploaded'))
  } catch {
    toast.danger(t('backups.upload.invalid'))
  } finally {
    uploading.value = false
  }
}

/** RESTAURAR es destructivo (machaca la BBDD actual): doble confirmación. */
async function restore(backup: BackupRow) {
  const first = await confirm({
    message: t('backups.confirmRestore', { name: backup.file }),
    confirmLabel: t('backups.restore'),
    cancelLabel: t('common.cancel'),
    variant: 'danger',
  })
  if (!first) return
  const second = await confirm({
    message: t('backups.confirmRestoreAgain'),
    confirmLabel: t('backups.confirmRestoreLabel'),
    cancelLabel: t('common.cancel'),
    variant: 'danger',
  })
  if (!second) return
  restoring.value = true
  try {
    await api.post(`/admin/backups/${backup.file}/restore`)
    toast.success(t('backups.toast.restored'))
    await load()
  } catch {
    toast.danger(t('common.errors.action'))
  } finally {
    restoring.value = false
  }
}

/** Descarga autenticada: el zip llega por la API con el token. */
async function download(backup: BackupRow) {
  try {
    const { data } = await api.get(`/admin/backups/${backup.file}/download`, {
      responseType: 'blob',
    })
    const url = URL.createObjectURL(data)
    const link = document.createElement('a')
    link.href = url
    link.download = backup.file
    link.click()
    URL.revokeObjectURL(url)
  } catch {
    toast.danger(t('common.errors.action'))
  }
}

async function remove(backup: BackupRow) {
  const ok = await confirm({
    message: t('backups.confirmDelete', { name: backup.file }),
    confirmLabel: t('common.actions.delete'),
    cancelLabel: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await api.delete(`/admin/backups/${backup.file}`)
    if (selectedFile.value === backup.file) selectedFile.value = null
    toast.success(t('backups.toast.deleted'))
    await load()
  } catch {
    toast.danger(t('common.errors.action'))
  }
}

/** Chip del origen: manual (acento), automática (azul), subida (ámbar). */
function originChipClass(backup: BackupRow): string {
  if (backup.origin === 'auto') return 'is-info'
  if (backup.origin === 'upload') return 'is-missing'
  return ''
}

function formatDate(iso: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(
    new Date(iso),
  )
}

function formatSize(bytes: number): string {
  if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  if (bytes >= 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${bytes} B`
}

onMounted(load)
</script>

<template>
  <div class="backups-view">
    <div class="list-view__top">
      <BaseButton :disabled="creating || pending" @click="create">
        <template #icon><Plus :size="16" /></template>
        {{ t('backups.create') }}
      </BaseButton>
    </div>

    <p class="backups-view__hint">{{ t('backups.hint') }}</p>

    <!-- Copia en curso (en cola): el listado se refresca solo -->
    <p v-if="pending" class="backups-view__pending" role="status">
      {{ t('backups.pending') }}
    </p>

    <!-- Copia automática + subir copia, lado a lado (columna en estrecho) -->
    <div class="backups-view__cards">
      <section v-if="schedule" class="backups-view__card">
        <h2>{{ t('backups.schedule.title') }}</h2>
        <BaseCheckbox v-model="schedule.auto" :label="t('backups.schedule.auto')" />
        <div v-if="schedule.auto" class="backups-view__schedule-fields">
          <BaseSelect
            v-model="schedule.frequency"
            :label="t('backups.schedule.frequency')"
            :options="frequencyOptions"
          />
          <BaseSelect
            v-if="schedule.frequency === 'weekly'"
            :model-value="String(schedule.weekday)"
            :label="t('backups.schedule.weekday')"
            :options="weekdayOptions"
            @update:model-value="(v) => (schedule!.weekday = Number(v))"
          />
          <BaseInput v-model="schedule.time" type="time" :label="t('backups.schedule.time')" />
          <NumericInput
            v-model="schedule.keep_days"
            :label="t('backups.schedule.keepDays')"
            :min="1"
            :max="365"
          />
        </div>
        <div>
          <BaseButton :disabled="savingSchedule" @click="saveSchedule">
            <template #icon><Save :size="14" /></template>
            {{ t('common.save') }}
          </BaseButton>
        </div>
      </section>

      <section class="backups-view__card">
        <h2>{{ t('backups.upload.title') }}</h2>
        <p class="backups-view__hint">{{ t('backups.upload.hint') }}</p>
        <input
          ref="uploadInput"
          type="file"
          accept=".zip"
          class="backups-view__upload-input"
          @change="onUploadChange"
        />
        <div class="backups-view__upload-row">
          <BaseButton variant="secondary" @click="uploadInput?.click()">
            <template #icon><FileUp :size="14" /></template>
            {{ t('backups.upload.choose') }}
          </BaseButton>
          <span v-if="uploadFile" class="backups-view__upload-name">{{ uploadFile.name }}</span>
        </div>
        <div>
          <BaseButton :disabled="!uploadFile || uploading" @click="upload">
            <template #icon><FileUp :size="14" /></template>
            {{ t('backups.upload.submit') }}
          </BaseButton>
        </div>
      </section>
    </div>

    <p v-if="!loading && !backups.length" class="backups-view__empty">{{ t('backups.empty') }}</p>

    <div class="pages-view__list">
      <article
        v-for="backup in backups"
        :key="backup.file"
        class="pages-view__item"
        :class="{ 'is-active': selectedFile === backup.file }"
        @click="(e) => select(backup, e)"
      >
        <strong class="backups-view__name">{{ backup.file }}</strong>
        <span class="backups-view__date">{{ formatDate(backup.date) }}</span>
        <span class="pages-view__chips">
          <span :class="['chip', originChipClass(backup)]">
            {{ t(`backups.origin.${backup.origin}`) }}
          </span>
          <span class="chip">{{ formatSize(backup.size) }}</span>
        </span>
      </article>
    </div>

    <!-- Acciones de la copia seleccionada, en el panel derecho -->
    <Teleport defer to="#right-sidebar-target">
      <div class="manager-panel">
        <p v-if="!selected" class="manager-panel__empty">{{ t('backups.panelEmpty') }}</p>
        <template v-else>
          <p class="manager-panel__kicker">{{ t('backups.panelTitle') }}</p>

          <!-- Acciones PRIMERO; después, secciones separadas (patrón panel) -->
          <div class="manager-detail__actions">
            <BaseButton @click="download(selected)">
              <template #icon><Download :size="14" /></template>
              {{ t('backups.download') }}
            </BaseButton>
            <BaseButton variant="warning" :disabled="restoring" @click="restore(selected)">
              <template #icon><RotateCcw :size="14" /></template>
              {{ t('backups.restore') }}
            </BaseButton>
            <BaseButton variant="danger" @click="remove(selected)">
              <template #icon><Trash2 :size="14" /></template>
              {{ t('common.actions.delete') }}
            </BaseButton>
          </div>

          <hr class="manager-panel__divider" />

          <h3 class="manager-detail__title">{{ selected.file }}</h3>

          <p class="manager-detail__meta">
            <strong>{{ t('backups.fields.origin') }}</strong>
            {{ t(`backups.origin.${selected.origin}`) }}
          </p>
          <p class="manager-detail__meta">
            <strong>{{ t('backups.fields.date') }}</strong> {{ formatDate(selected.date) }}
          </p>
          <p class="manager-detail__meta">
            <strong>{{ t('backups.fields.size') }}</strong> {{ formatSize(selected.size) }}
          </p>

          <!-- Qué hace (y qué NO hace) restaurar: límites documentados -->
          <hr class="manager-panel__divider" />
          <p class="manager-panel__empty">{{ t('backups.restoreHint') }}</p>
        </template>
      </div>
    </Teleport>
  </div>
</template>
