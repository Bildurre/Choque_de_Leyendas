import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/lib/api'

export interface Icon {
  id: number
  name: string
  slug: string
  url: string | null
}

// Biblioteca de iconos del juego (la sirve el motor: GET /icons).
export const useIconsStore = defineStore('icons', () => {
  const icons = ref<Icon[]>([])
  const loaded = ref(false)
  let inflight: Promise<void> | null = null

  function load(force = false): Promise<void> {
    if (loaded.value && !force) return Promise.resolve()
    inflight ??= api
      .get('/icons')
      .then(({ data }) => {
        icons.value = data.data
        loaded.value = true
      })
      .finally(() => {
        inflight = null
      })
    return inflight
  }

  return { icons, loaded, load }
})
