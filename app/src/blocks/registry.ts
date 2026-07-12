import type { Component } from 'vue'
import { motorBlockComponents } from '@edc-motor/ui'
import CountersListBlock from './CountersListBlock.vue'
import GameModesBlock from './GameModesBlock.vue'

// Registro de componentes de bloque de la web pública (guía §3): los de
// presentación vienen del motor; aquí se añaden los con-datos de ESTE juego.
// La clave casa con BlockType::$key del backend (Blocks::register en la api).
export const blockRegistry: Record<string, Component> = {
  ...motorBlockComponents,
  'counters-list': CountersListBlock,
  'game-modes': GameModesBlock,
}
