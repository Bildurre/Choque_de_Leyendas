// Tipos de las entidades del juego tal y como las sirven los Resources de la
// API de admin (traducciones completas por locale, para editar). Extiende
// EntityBase con los campos de cada entidad de TU juego.

export type Translations = Record<string, string>

export interface EntityBase {
  id: number
  slug: Translations
  image: string | null
  is_published: boolean
  deleted_at: string | null
  /** PNG generados por idioma (solo entidades renderizables). */
  previews?: Record<string, string>
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}
