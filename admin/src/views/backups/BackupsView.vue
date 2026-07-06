<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Download, Plus, Save, Trash2 } from '@lucide/vue'
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

// Copias de seguridad (doc 06): crear con un clic, listar, descargar y
// borrar; la fila entera selecciona y el panel derecho trae las acciones.
// La copia AUTOMÁTICA (frecuencia, hora, retención) se configura aquí y la
// programa el scheduler del motor.
interface BackupRow {
  file: string
  date: string
  size: number
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
const selectedFile = ref<string | null>(null)
const schedule = ref<BackupSchedule | null>(null)
const savingSchedule = ref(false)

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

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/backups')
    backups.value = data.data
    schedule.value = data.schedule
  } catch {
    toast.danger(t('common.errors.load'))
  } finally {
    loading.value = false
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

async function create() {
  creating.value = true
  try {
    const { data, status } = await api.post('/admin/backups')
    backups.value = data.data

    // 202 = en cola (BBDD grandes, DC-16): sondea hasta que aparezca la
    // copia nueva (o desiste a los ~3 minutos).
    if (status === 202) {
      const before = backups.value.length
      for (let i = 0; i < 90; i++) {
        await new Promise((resolve) => setTimeout(resolve, 2000))
        const { data: fresh } = await api.get('/admin/backups')
        backups.value = fresh.data
        if (backups.value.length > before) break
      }
    }

    toast.success(t('backups.toast.created'))
  } catch {
    toast.danger(t('common.errors.action'))
  } finally {
    creating.value = false
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
      <BaseButton :disabled="creating" @click="create">
        <template #icon><Plus :size="16" /></template>
        {{ t('backups.create') }}
      </BaseButton>
    </div>

    <p class="backups-view__hint">{{ t('backups.hint') }}</p>

    <!-- Copia automática: la programa el scheduler del motor -->
    <section v-if="schedule" class="backups-view__schedule">
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
            <BaseButton variant="danger" @click="remove(selected)">
              <template #icon><Trash2 :size="14" /></template>
              {{ t('common.actions.delete') }}
            </BaseButton>
          </div>

          <hr class="manager-panel__divider" />

          <h3 class="manager-detail__title">{{ selected.file }}</h3>

          <p class="manager-detail__meta">
            <strong>{{ t('backups.fields.date') }}</strong> {{ formatDate(selected.date) }}
          </p>
          <p class="manager-detail__meta">
            <strong>{{ t('backups.fields.size') }}</strong> {{ formatSize(selected.size) }}
          </p>
        </template>
      </div>
    </Teleport>
  </div>
</template>
