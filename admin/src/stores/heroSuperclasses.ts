import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/lib/api'
import type { TaxonomyOption } from '@juego/shared'

// Lista ligera de superclases para selectores (p. ej. la superclase de una
// clase de héroe o de un tipo de carta).
export const useHeroSuperclassesStore = defineStore('heroSuperclasses', () => {
  const options = ref<TaxonomyOption[]>([])
  const loaded = ref(false)
  let inflight: Promise<void> | null = null

  function loadOptions(force = false): Promise<void> {
    if (loaded.value && !force) return Promise.resolve()
    inflight ??= api
      .get('/admin/hero-superclasses/options')
      .then(({ data }) => {
        options.value = data.data
        loaded.value = true
      })
      .finally(() => {
        inflight = null
      })
    return inflight
  }

  return { options, loaded, loadOptions }
})
