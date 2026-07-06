import type { Component } from 'vue'
import ProfileSection from '@/views/account/ProfileSection.vue'
import SecuritySection from '@/views/account/SecuritySection.vue'
import PrintSection from './PrintSection.vue'

// Panel de usuario extensible (doc 10): el motor aporta las secciones base
// (datos y contraseña, doc 05) y CADA JUEGO cuelga aquí las suyas — menú,
// ruta y componente en una entrada. El router monta una child route por
// sección bajo /:locale/cuenta y AccountLayout pinta el menú.
export interface AccountSection {
  key: string
  /** Segmento bajo cuenta/ ('' = la portada del panel). */
  path: string
  /** Nombre de la ruta (los guards usan 'account' y 'security'). */
  name: string
  titleKey: string
  component: Component
}

const baseSections: AccountSection[] = [
  {
    key: 'profile',
    path: '',
    name: 'account',
    titleKey: 'account.sections.profile',
    component: ProfileSection,
  },
  {
    key: 'security',
    path: 'seguridad',
    name: 'security',
    titleKey: 'account.sections.security',
    component: SecuritySection,
  },
]

// --- Secciones de ESTE juego ---
const gameSections: AccountSection[] = [
  {
    key: 'print',
    path: 'imprimir',
    name: 'account-print',
    titleKey: 'account.sections.print',
    component: PrintSection,
  },
]

export const accountSections: AccountSection[] = [...baseSections, ...gameSections]
