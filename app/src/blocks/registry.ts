import type { Component } from 'vue'
import { motorBlockComponents } from '@edc-motor/ui'
import CountersListBlock from './CountersListBlock.vue'
import DownloadsBlock from './DownloadsBlock.vue'
import EntityIndexBlock from './EntityIndexBlock.vue'
import GameModesBlock from './GameModesBlock.vue'
import GameRelatedBlock from './GameRelatedBlock.vue'

// Registro de componentes de bloque de la web pública (guía §3): los de
// presentación vienen del motor; aquí se añaden los con-datos de ESTE juego.
// La clave casa con BlockType::$key del backend (Blocks::register en la api).
// `related` se SUSTITUYE por el del juego: facción (sin preview PNG) se
// pinta como tarjeta CSS; el resto delega en el BlockRelated del motor.
export const blockRegistry: Record<string, Component> = {
  ...motorBlockComponents,
  related: GameRelatedBlock,
  'counters-list': CountersListBlock,
  'game-modes': GameModesBlock,
  'entity-index': EntityIndexBlock,
  downloads: DownloadsBlock,
}
