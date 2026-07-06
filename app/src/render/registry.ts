import type { Component } from 'vue'

// Registro de componentes visuales por entidad renderizable (guía §5): el
// segmento de /_render/:entity/:id. Debe casar con el PreviewRegistry del
// backend (Previews::register en el AppServiceProvider de la api). Los
// componentes de carta viven en @juego/shared (se pintan igual aquí, en el
// admin y en el PNG).
export const renderRegistry: Record<string, Component> = {}
