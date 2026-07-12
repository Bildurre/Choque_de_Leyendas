import type { Component } from 'vue'
import {
  CardRender,
  CounterRender,
  FactionDeckRender,
  FactionRender,
  HeroRender,
} from '@juego/shared'

// Registro de componentes visuales por entidad renderizable (guía §5): el
// segmento de /_render/:entity/:id. Debe casar con el PreviewRegistry del
// backend (Previews::register en el AppServiceProvider de la api). Los
// componentes de carta viven en @juego/shared (se pintan igual aquí, en el
// admin y en el PNG).
export const renderRegistry: Record<string, Component> = {
  card: CardRender, // carta jugable 750x1050
  hero: HeroRender, // carta de héroe 750x1050
  counter: CounterRender, // contador redondo 300x300
  faction: FactionRender, // emblema de facción 750x1050 (proporción 5/7)
  'faction-deck': FactionDeckRender, // ficha de mazo 750x1050 (proporción 5/7)
}
