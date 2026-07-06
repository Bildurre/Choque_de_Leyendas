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

export interface CollectionPdf {
  id: number
  status: string
  url: string | null
  error: string | null
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
  const pdf = ref<CollectionPdf | null>(null)
  const generating = ref(false)
  const loaded = ref(false)

  const count = computed(() => items.value.reduce((sum, item) => sum + item.copies, 0))

  function has(entity: string, id: number): boolean {
    return items.value.some((i) => i.entity === entity && i.entity_id === id)
  }

  async function load() {
    try {
      const { data } = await api.get('/pdf-collection')
      items.value = data.data
    } catch {
      items.value = []
    } finally {
      loaded.value = true
    }
  }

  async function add(entity: string, id: number, copies?: number) {
    const { data } = await api.post('/pdf-collection/items', { entity, id, copies })
    items.value = data.data
  }

  async function remove(item: CollectionItem) {
    const { data } = await api.delete(`/pdf-collection/items/${item.id}`)
    items.value = data.data
  }

  async function clear() {
    await api.delete('/pdf-collection')
    items.value = []
  }

  /** Genera el PDF temporal (en cola) y sondea hasta ready/failed. */
  let poll: ReturnType<typeof setInterval> | null = null

  async function generate(locale: string): Promise<void> {
    if (poll) clearInterval(poll)
    pdf.value = null
    generating.value = true
    const { data } = await api.post('/pdf-collection/generate', { locale })
    pdf.value = data.data
    await new Promise<void>((resolve) => {
      poll = setInterval(async () => {
        if (!pdf.value) return
        try {
          const { data: status } = await api.get(`/pdf-collection/pdfs/${pdf.value.id}`)
          pdf.value = status.data
        } catch {
          if (pdf.value) pdf.value = { ...pdf.value, status: 'failed' }
        }
        if (pdf.value?.status !== 'pending') {
          if (poll) clearInterval(poll)
          generating.value = false
          resolve()
        }
      }, 1000)
    })
  }

  return { items, pdf, generating, loaded, count, has, load, add, remove, clear, generate }
})
