<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { MailCheck, Plus, SquarePen, Trash2 } from '@lucide/vue'
import { BaseButton, BasePagination, useConfirm, useToast } from '@edc-motor/ui'
import type { SortValue } from '@edc-motor/ui'
import { ManagerCard, useCardDeselect, useRightSidebar } from '@edc-motor/admin-kit'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import ListToolbar from '@/components/ListToolbar.vue'
import UserFormModal, { type UserRow } from '@/components/users/UserFormModal.vue'

// Gestión de usuarios (doc 05), lo típico y básico: listar con búsqueda,
// crear (con rol), editar y borrar. Patrón kontuan: tarjetas en grid, la
// tarjeta entera selecciona y el panel derecho trae las acciones + info.
const { t, te } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const auth = useAuthStore()

const sidebar = useRightSidebar()
sidebar.useRegister(t('users.panelTitle'))

const users = ref<UserRow[]>([])
const loading = ref(true)
const search = ref('')
// Los usuarios se listan alfabéticamente por defecto (contrato de `sort`).
const sort = ref<SortValue>('name')
// Paginación del servidor (paginate del motor); escribir en `page` navega.
const currentPage = ref(1)
const pages = ref(1)
const page = computed({
  get: () => currentPage.value,
  set: (n: number) => {
    load(n)
  },
})
const formOpen = ref(false)
const editing = ref<UserRow | null>(null)
const selectedId = ref<number | null>(null)

const selected = computed(() => users.value.find((u) => u.id === selectedId.value) ?? null)
const isSelf = computed(() => selected.value?.id === auth.user?.id)

async function load(pageNumber = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/admin/users', {
      params: { search: search.value, sort: sort.value, page: pageNumber },
    })
    users.value = data.data
    currentPage.value = data.meta?.current_page ?? 1
    pages.value = data.meta?.last_page ?? 1
  } catch {
    toast.danger(t('common.errors.load'))
  } finally {
    loading.value = false
  }
}

// La búsqueda y el orden vuelven a la página 1 (debounce compartido).
let timer: ReturnType<typeof setTimeout> | null = null
watch([search, sort], () => {
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => load(1), 250)
})
onBeforeUnmount(() => {
  if (timer) clearTimeout(timer)
})

function select(user: UserRow) {
  selectedId.value = user.id
  sidebar.reveal()
}

// Click en la zona vacía del contenido (fuera de una card o control):
// deselecciona y el panel vuelve a su mensaje de "selecciona".
useCardDeselect(() => (selectedId.value = null))

function openCreate() {
  editing.value = null
  formOpen.value = true
}

function openEdit(user: UserRow) {
  editing.value = user
  formOpen.value = true
}

/** Interruptor del panel: marca/desmarca el email como verificado. */
async function toggleVerified() {
  if (!selected.value) return
  try {
    const { data } = await api.post(`/admin/users/${selected.value.id}/toggle-verified`)
    selected.value.email_verified = data.data.email_verified
    toast.success(t('users.toast.saved'))
  } catch {
    toast.danger(t('common.errors.action'))
  }
}

async function remove(user: UserRow) {
  const ok = await confirm({
    message: t('users.confirmDelete', { name: user.name }),
    confirmLabel: t('common.actions.delete'),
    cancelLabel: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await api.delete(`/admin/users/${user.id}`)
    if (selectedId.value === user.id) selectedId.value = null
    toast.success(t('users.toast.deleted'))
    await load(currentPage.value)
  } catch {
    toast.danger(t('common.errors.action'))
  }
}

function roleLabel(user: UserRow): string {
  return (
    (user.roles ?? [])
      .map((role) => (te(`users.roles.${role}`) ? t(`users.roles.${role}`) : role))
      .join(', ') || '—'
  )
}

/** Color del chip de rol: admin en verde, editor en azul, resto neutro. */
function roleChipClass(user: UserRow): string {
  if (user.roles.includes('admin')) return 'is-ok'
  if (user.roles.includes('editor')) return 'is-info'
  return ''
}

onMounted(load)
</script>

<template>
  <div class="users-view">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('users.new') }}
      </BaseButton>
    </div>

    <!-- Barra del índice: búsqueda + toggles de ordenación -->
    <ListToolbar v-model="search" v-model:sort="sort" />
    <BasePagination
      v-model:page="page"
      :pages="pages"
      class="list-view__pagination"
      :prev-label="t('common.pagination.prev')"
      :next-label="t('common.pagination.next')"
      :of-label="t('common.pagination.of', { page, pages })"
    />

    <p v-if="!loading && !users.length" class="users-view__empty">{{ t('common.empty') }}</p>

    <div class="manager-grid">
      <!-- Como EntityCard: BADGES (rol + verificado) arriba, meta debajo -->
      <ManagerCard
        v-for="user in users"
        :key="user.id"
        :title="user.name"
        :active="selectedId === user.id"
        @select="select(user)"
      >
        <template #badges>
          <span class="chip" :class="roleChipClass(user)">{{ roleLabel(user) }}</span>
          <span v-if="user.email_verified" class="chip is-ok">{{ t('users.verifiedBadge') }}</span>
          <span v-else class="chip is-missing">{{ t('users.unverified') }}</span>
        </template>
        <template #meta>
          <span class="users-view__email">{{ user.email }}</span>
        </template>
      </ManagerCard>
    </div>

    <BasePagination
      v-model:page="page"
      :pages="pages"
      class="list-view__pagination list-view__pagination--bottom"
      :prev-label="t('common.pagination.prev')"
      :next-label="t('common.pagination.next')"
      :of-label="t('common.pagination.of', { page, pages })"
    />

    <UserFormModal v-model="formOpen" :user="editing" @saved="load(currentPage)" />

    <!-- Acciones del usuario seleccionado, en el panel derecho -->
    <Teleport defer to="#right-sidebar-target">
      <div class="manager-panel">
        <p v-if="!selected" class="manager-panel__empty">{{ t('users.panelEmpty') }}</p>
        <template v-else>
          <p class="manager-panel__kicker">{{ t('users.panelTitle') }}</p>

          <!-- Acciones PRIMERO (el interruptor de verificado arriba);
               después, secciones separadas (patrón panel) -->
          <div class="manager-detail__actions">
            <BaseButton
              variant="success"
              :class="selected.email_verified ? 'is-on' : 'is-off'"
              :aria-pressed="selected.email_verified"
              @click="toggleVerified"
            >
              <template #icon><MailCheck :size="14" /></template>
              {{ t('users.verifiedBadge') }}
            </BaseButton>
            <BaseButton variant="info" @click="openEdit(selected)">
              <template #icon><SquarePen :size="14" /></template>
              {{ t('common.actions.edit') }}
            </BaseButton>
            <BaseButton v-if="!isSelf" variant="danger" @click="remove(selected)">
              <template #icon><Trash2 :size="14" /></template>
              {{ t('common.actions.delete') }}
            </BaseButton>
          </div>

          <hr class="manager-panel__divider" />

          <h3 class="manager-detail__title">{{ selected.name }}</h3>

          <!-- Estado del interruptor, en texto -->
          <p class="manager-detail__meta">
            <strong>{{ t('users.verified') }}</strong>
            {{ selected.email_verified ? t('common.yes') : t('common.no') }}
          </p>

          <p class="manager-detail__meta">
            <strong>{{ t('users.fields.email') }}</strong> {{ selected.email }}
          </p>
          <p class="manager-detail__meta">
            <strong>{{ t('users.fields.role') }}</strong> {{ roleLabel(selected) }}
          </p>
          <p v-if="isSelf" class="manager-panel__empty">{{ t('users.selfHint') }}</p>
        </template>
      </div>
    </Teleport>
  </div>
</template>
