<script setup lang="ts">
import { computed, ref } from 'vue'
import { Check, FilePlus } from '@lucide/vue'
import { useI18n } from 'vue-i18n'
import { useCollectionStore } from '@/stores/collection'

// Botón "añadir a la colección para imprimir" (doc 02): vive sobre las
// cartas de los listados y en los detalles. Si ya está, lo indica.
const props = defineProps<{ entity: string; id: number; label?: boolean }>()

const { t } = useI18n()
const collection = useCollectionStore()
const busy = ref(false)

const added = computed(() => collection.has(props.entity, props.id))

async function add() {
  if (added.value || busy.value) return
  busy.value = true
  try {
    await collection.add(props.entity, props.id)
  } catch {
    // el gestor de la colección ya avisa de sus errores; aquí basta soltar
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <button
    class="add-to-collection"
    :class="{ 'is-added': added, 'has-label': label }"
    type="button"
    :title="added ? t('collection.inCollection') : t('collection.add')"
    :disabled="busy"
    @click.prevent.stop="add"
  >
    <!-- Icono a doble tamaño cuando va solo (flotante sobre las cartas);
         con etiqueta, algo más contenido -->
    <Check v-if="added" :size="label ? 20 : 32" />
    <!-- "pdf-add": añadir el elemento a tu PDF personalizado -->
    <FilePlus v-else :size="label ? 20 : 32" />
    <span v-if="label">{{ added ? t('collection.inCollection') : t('collection.add') }}</span>
  </button>
</template>
