import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/lib/api'

// Colección "para imprimir" (doc 02), para logueados E invitados (CDL): la
// SPA genera un token de invitado (uuid en localStorage) y lo manda SIEMPRE
// en X-Collection-Token; el backend lo ignora si viaja un token Sanctum.
export interface CollectionItem {
  id: number
  entity: string
  entity_id: number
  copies: number
  label: string | null
  preview: string | null
  missing: boolean
}

// PDF temporal de la colección (clave `generated` del índice del motor):
// los 'ready' dan el enlace de descarga (persistente tras recargar, hasta
// caducar); un 'pending' permite retomar el sondeo de la generación.
export interface CollectionPdf {
  id: number
  status: string
  filename: string
  locale: string
  url: string | null
  size: number | null
  generated_at: string | null
  expires_at: string | null
}

const TOKEN_KEY = 'edc_collection_token'

function guestToken(): string {
  let token = localStorage.getItem(TOKEN_KEY)
  if (!token) {
    token = crypto.randomUUID()
    localStorage.setItem(TOKEN_KEY, token)
  }
  return token
}

export const useCollectionStore = defineStore('collection', () => {
  api.defaults.headers.common['X-Collection-Token'] = guestToken()

  const items = ref<CollectionItem[]>([])
  const generated = ref<CollectionPdf[]>([])
  const generating = ref(false)
  const loaded = ref(false)

  const count = computed(() => items.value.reduce((sum, item) => sum + item.copies, 0))

  // Los PDF ya listos para descargar (sección superior de «Mi colección»).
  const readyPdfs = computed(() => generated.value.filter((pdf) => pdf.status === 'ready'))

  function has(entity: string, id: number): boolean {
    return items.value.some((i) => i.entity === entity && i.entity_id === id)
  }

  /** Vuelca la respuesta del índice (items + PDFs generados vigentes). */
  function apply(data: { data: CollectionItem[]; generated?: CollectionPdf[] }) {
    items.value = data.data
    generated.value = data.generated ?? []
  }

  async function load() {
    try {
      const { data } = await api.get('/pdf-collection')
      apply(data)
      // Una generación quedó a medias (recarga en pleno sondeo): se retoma.
      const pending = generated.value.find((pdf) => pdf.status === 'pending')
      if (pending && !generating.value) {
        generating.value = true
        void poll(pending.id).finally(() => {
          generating.value = false
        })
      }
    } catch {
      items.value = []
      generated.value = []
    } finally {
      loaded.value = true
    }
  }

  async function add(entity: string, id: number, copies?: number) {
    const { data } = await api.post('/pdf-collection/items', { entity, id, copies })
    apply(data)
  }

  async function remove(item: CollectionItem) {
    const { data } = await api.delete(`/pdf-collection/items/${item.id}`)
    apply(data)
  }

  async function clear() {
    await api.delete('/pdf-collection')
    items.value = []
  }

  /** Sondea un PDF temporal hasta ready/failed y refresca el índice. */
  function poll(id: number): Promise<string> {
    return new Promise((resolve) => {
      const timer = setInterval(async () => {
        let status: string
        try {
          const { data } = await api.get(`/pdf-collection/pdfs/${id}`)
          status = data.data.status
        } catch {
          status = 'failed'
        }
        if (status !== 'pending') {
          clearInterval(timer)
          if (status === 'ready') {
            // Patrón carrito: la colección se vació al pulsar «Generar»;
            // se vacía también en el servidor para que siga vacía tras
            // recargar (el PDF ya lleva su propia copia de los items).
            await api.delete('/pdf-collection').catch(() => {})
          }
          // El índice trae el PDF nuevo con su URL (y, si falló, restaura
          // los items que solo se habían limpiado visualmente).
          await load()
          resolve(status)
        }
      }, 1000)
    })
  }

  /**
   * Genera el PDF temporal (202 + cola en el motor): limpia la colección
   * visualmente, deja `generating` activo mientras dura el sondeo y
   * devuelve el estado final ('ready' | 'failed').
   */
  async function generate(locale: string): Promise<string> {
    generating.value = true
    // Se limpia YA visualmente (patrón carrito); si algo falla por el
    // camino, load() la restaura (los items siguen en el servidor).
    items.value = []
    try {
      const { data } = await api.post('/pdf-collection/generate', { locale })
      return await poll(data.data.id)
    } catch {
      await load().catch(() => {})
      return 'failed'
    } finally {
      generating.value = false
    }
  }

  return {
    items,
    generated,
    readyPdfs,
    generating,
    loaded,
    count,
    has,
    load,
    add,
    remove,
    clear,
    generate,
  }
})
