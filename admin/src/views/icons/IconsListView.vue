<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Plus, SquarePen, Trash2 } from '@lucide/vue'
import { BaseGrid, EntityCard, EmptyState } from '@edc-motor/admin-kit'
import {
  BaseButton,
  IconButton,
  EditModal,
  BaseInput,
  ImageUpload,
  useToast,
  useConfirm,
} from '@edc-motor/ui'
import { api } from '@/lib/api'
import { fieldErrors } from '@/lib/apiError'
import { useIconsStore, type Icon } from '@/stores/icons'

const { t } = useI18n()
const store = useIconsStore()
const toast = useToast()
const { confirm } = useConfirm()

const loading = ref(false)

async function reload() {
  loading.value = true
  try {
    await store.load(true)
  } catch {
    toast.danger(t('common.errors.load'))
  } finally {
    loading.value = false
  }
}
onMounted(reload)

// --- Modal de alta / edición (mismo formulario; la imagen es opcional al editar) ---
const open = ref(false)
const saving = ref(false)
const editing = ref<Icon | null>(null)
const errors = reactive<Record<string, string>>({})
const form = reactive<{ name: string; image: File | null }>({ name: '', image: null })

const modalTitle = computed(() => (editing.value ? t('icons.edit') : t('icons.new')))

function clearErrors() {
  for (const k of Object.keys(errors)) delete errors[k]
}

function openCreate() {
  editing.value = null
  form.name = ''
  form.image = null
  clearErrors()
  open.value = true
}

function openEdit(icon: Icon) {
  editing.value = icon
  form.name = icon.name
  form.image = null // solo se sube si se elige una nueva
  clearErrors()
  open.value = true
}

async function save() {
  clearErrors()
  // Validación mínima en cliente (evita el 422 y marca los campos).
  if (!form.name.trim()) errors.name = t('common.required')
  if (!editing.value && !form.image) errors.image = t('common.required')
  if (errors.name || errors.image) return

  saving.value = true
  try {
    const fd = new FormData()
    fd.append('name', form.name)
    if (form.image) fd.append('image', form.image)
    if (editing.value) {
      await api.post(`/admin/icons/${editing.value.id}`, fd)
      toast.success(t('icons.toast.updated'))
    } else {
      await api.post('/admin/icons', fd)
      toast.success(t('icons.toast.created'))
    }
    open.value = false
    await reload()
  } catch (e) {
    // Errores de validación por campo (traducidos) + aviso genérico. Nunca se
    // muestra el mensaje crudo del servidor.
    Object.assign(errors, fieldErrors(e))
    toast.danger(t('icons.toast.saveError'))
  } finally {
    saving.value = false
  }
}

async function del(icon: Icon) {
  const ok = await confirm({
    title: t('icons.confirmDelete.title'),
    message: t('icons.confirmDelete.message', { name: icon.name }),
    confirmLabel: t('common.actions.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await api.delete(`/admin/icons/${icon.id}`)
    toast.success(t('icons.toast.deleted'))
    await reload()
  } catch {
    toast.danger(t('common.errors.action'))
  }
}
</script>

<template>
  <div class="icons">
    <div class="list-view__top">
      <BaseButton @click="openCreate">
        <template #icon><Plus :size="16" /></template>
        {{ t('icons.new') }}
      </BaseButton>
    </div>

    <EmptyState v-if="!loading && !store.icons.length" :title="t('common.empty')" />

    <!-- Grid denso (el doble de columnas que `cards`): las cards de icono
         son pequeñas y se listan TODOS los iconos, sin paginación. -->
    <BaseGrid v-else preset="cards-dense" gap="md">
      <EntityCard v-for="icon in store.icons" :key="icon.id" :title="icon.name">
        <template #media>
          <div class="icon-tile"><img v-if="icon.url" :src="icon.url" :alt="icon.name" /></div>
        </template>
        <template #actions>
          <IconButton variant="success" :title="t('common.actions.edit')" @click="openEdit(icon)"
            ><SquarePen :size="18"
          /></IconButton>
          <IconButton variant="danger" :title="t('common.actions.delete')" @click="del(icon)"
            ><Trash2 :size="18"
          /></IconButton>
        </template>
      </EntityCard>
    </BaseGrid>

    <EditModal
      v-model="open"
      :title="modalTitle"
      :loading="saving"
      :submit-label="t('common.save')"
      :cancel-label="t('common.cancel')"
      @submit="save"
    >
      <BaseInput v-model="form.name" :label="t('icons.nameLabel')" required :error="errors.name" />
      <!-- Al editar se muestra la imagen ACTUAL del icono; elegir otra solo
           la sustituye al guardar (la imagen es obligatoria: sin "quitar") -->
      <ImageUpload
        v-model="form.image"
        :current-url="editing?.url ?? null"
        :label="editing ? t('icons.imageReplaceLabel') : t('icons.imageLabel')"
        accept=".svg,.png,.jpg,.jpeg,.webp"
        :max-size="2"
        :drag-text="t('common.imageDrag')"
        :hint-text="t('icons.imageHint')"
        :too-large-text="t('common.fileTooLarge')"
        :invalid-type-text="t('common.fileType')"
        :error="errors.image"
      />
    </EditModal>
  </div>
</template>
