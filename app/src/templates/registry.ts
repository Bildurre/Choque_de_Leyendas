import type { Component } from 'vue'
import DefaultTemplate from './DefaultTemplate.vue'
import LandingTemplate from './LandingTemplate.vue'

// Plantillas de página de ESTE juego: la clave viaja en el payload público
// (columna `template` de la página; catálogo en config motor.content.templates,
// ampliado en el AppServiceProvider de la API). El componente envuelve los
// bloques (slot) y decide el layout; una clave desconocida cae en 'default'.
export const templateRegistry: Record<string, Component> = {
  default: DefaultTemplate,
  landing: LandingTemplate,
}

export function templateFor(key: string | null | undefined): Component {
  return templateRegistry[key ?? 'default'] ?? templateRegistry.default
}
