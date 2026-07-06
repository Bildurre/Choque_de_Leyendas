import { ref } from 'vue'

// Último tramo DINÁMICO de la breadcrumb (p. ej. el nombre del single, que
// no puede salir del meta estático de la ruta). La vista lo fija al cargar
// su entidad y lo limpia al desmontar; App.vue lo añade a las migas.
const tail = ref<string | null>(null)

export function usePageCrumb() {
  function set(label: string) {
    tail.value = label
  }

  function clear() {
    tail.value = null
  }

  return { tail, set, clear }
}
