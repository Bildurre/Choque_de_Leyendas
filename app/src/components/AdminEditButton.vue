<script setup lang="ts">
import { computed } from 'vue'
import { Pencil } from '@lucide/vue'
import { useI18n } from 'vue-i18n'
import { adminEntityUrl } from '@/lib/adminUrl'
import { useAuthStore } from '@/stores/auth'
import { useLocalesStore } from '@/stores/locales'

// Botón "editar en la administración": SOLO para editores/administradores
// logueados (can_access_admin, mismo check que el enlace admin del header).
// Lleva al single del elemento en el admin de CdL EN PESTAÑA NUEVA y, en el
// clic normal, con traspaso de sesión (handoff, un solo uso): la pestaña se
// abre SÍNCRONA (anti-bloqueador de popups) y se navega al llegar el
// código; si falla, el admin pedirá login. El href simple queda para
// clic-central/ctrl (sin traspaso). Vive junto al añadir/descargar de los
// singles y flotando sobre las tarjetas de los índices.
const props = defineProps<{
  /** Key de la sección en el registry de la app (cards/heroes/factions/decks). */
  section: string
  slug: string | null | undefined
  /** Con etiqueta (headers de los singles); sin ella, solo icono (índices). */
  label?: boolean
}>()

const { t } = useI18n()
const auth = useAuthStore()
const locales = useLocalesStore()

const url = computed(() => adminEntityUrl(props.section, props.slug, locales.current))
const visible = computed(() => auth.user?.can_access_admin === true && url.value !== null)

async function open(event: MouseEvent) {
  event.preventDefault()
  const win = window.open('', '_blank')
  let target = url.value as string
  try {
    const code = await auth.requestHandoff()
    target = `${target}?handoff=${code}`
  } catch {
    // sin código: el admin pedirá login normal
  }
  if (win) win.location.href = target
  else window.open(target, '_blank')
}
</script>

<template>
  <a
    v-if="visible"
    class="admin-edit"
    :class="{ 'has-label': label }"
    :href="url ?? undefined"
    target="_blank"
    rel="noopener"
    :title="t('detail.editInAdmin')"
    :aria-label="t('detail.editInAdmin')"
    @click.stop="open"
  >
    <Pencil :size="label ? 20 : 26" />
    <span v-if="label">{{ t('detail.editInAdmin') }}</span>
  </a>
</template>
