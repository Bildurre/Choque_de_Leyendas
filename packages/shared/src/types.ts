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

/**
 * Forma mínima que exigen useEntityList/EntityPanel. EntityBase la cumple;
 * las taxonomías simples (sin slug, sin publicación) también.
 */
export interface EntityListItem {
  id: number
  deleted_at: string | null
  slug?: Translations
  image?: string | null
  is_published?: boolean
  previews?: Record<string, string>
}

/** Taxonomía simple: solo nombre traducible; se resuelve por id. */
export interface TaxonomyBase extends EntityListItem {
  name: Translations
}

// --- Taxonomías de héroes y ataques (cluster taxonomies-a) ---

/**
 * Taxonomía con nombre femenino OPCIONAL: se muestra solo junto a un héroe
 * de género femenino (y si el locale lo tiene); los listados usan `name`.
 */
export interface GenderedTaxonomy extends TaxonomyBase {
  name_female?: Translations
}

export type HeroSuperclass = GenderedTaxonomy
export type HeroRace = GenderedTaxonomy
export type AttackRange = TaxonomyBase
export type AttackSubtype = TaxonomyBase

export interface HeroClass extends GenderedTaxonomy {
  passive: Translations
  hero_superclass_id: number | null
  hero_superclass?: HeroSuperclass | null
}

/** Opción de un selector (endpoint options de una taxonomía). */
export interface TaxonomyOption {
  id: number
  name: Translations
}

/** Opción del selector de clases de héroe (con su superclase, para acotar). */
export interface HeroClassOption extends TaxonomyOption {
  hero_superclass_id: number | null
}

// --- Taxonomías de cartas y modos de juego (cluster taxonomies-b) ---

export type CardSubtype = TaxonomyBase

export interface CardType extends TaxonomyBase {
  /** Superclase asociada (única por tipo; p. ej. Técnica→Guerrero). */
  hero_superclass_id: number | null
  hero_superclass?: TaxonomyOption | null
  /** Muestra el select de subtipo en el form de cartas. */
  allows_subtypes: boolean
  /** Muestra tipo de equipo y manos en el form de cartas. */
  is_equipment: boolean
}

export interface EquipmentType extends TaxonomyBase {
  /** Las cartas de este tipo llevan manos (armas). */
  uses_hands: boolean
}

export interface EquipmentSubtype extends TaxonomyBase {
  /** Tipo de equipo al que pertenece (obligatorio). */
  equipment_type_id: number
  equipment_type?: EquipmentTypeOption | null
}

export interface GameMode extends TaxonomyBase {
  description: Translations
}

// --- Facciones y contadores (cluster faction-counter) ---

export interface Faction extends EntityBase {
  name: Translations
  lore_text: Translations
  epic_quote: Translations
  color: string
  /** Computado en servidor por luminancia YIQ del color. */
  text_is_dark: boolean
}

/** Opción del selector de facciones (endpoint options: id + name + color). */
export interface FactionOption {
  id: number
  name: Translations
  color: string
}

export type CounterType = 'boon' | 'bane'

/** Contador (sin slug: se resuelve por id). Renderizable a PNG. */
export interface Counter extends EntityListItem {
  name: Translations
  effect: Translations
  type: CounterType
  image: string | null
  is_published: boolean
}

// --- Héroes y habilidades (cluster hero-cluster) ---

export interface HeroAbility extends EntityListItem {
  name: Translations
  description: Translations
  attack_type: 'physical' | 'magical' | null
  attack_range_id: number | null
  attack_subtype_id: number | null
  attack_range?: TaxonomyOption | null
  attack_subtype?: TaxonomyOption | null
  area: boolean
  cost: string
}

/** Opción del selector de habilidades (endpoint options: id + name + cost). */
export interface HeroAbilityOption extends TaxonomyOption {
  cost: string
}

/** Habilidad activa dentro de un héroe (con la posición del pivot). */
export interface HeroAbilityRef {
  id: number
  name: Translations
  description: Translations
  cost: string
  attack_type: 'physical' | 'magical' | null
  area: boolean
  position: number
}

export interface Hero extends EntityBase {
  name: Translations
  lore_text: Translations
  epic_quote: Translations
  passive_name: Translations
  passive_description: Translations
  faction_id: number | null
  hero_race_id: number | null
  hero_class_id: number | null
  /** Facción en mínimo (con su color, que tiñe la tarjeta del listado). */
  faction?: FactionOption | null
  hero_race?: TaxonomyOption | null
  hero_class?: HeroClass | null
  /** Nombres de raza/clase YA resueltos con el género del héroe, por locale. */
  race_display?: Translations
  class_display?: Translations
  gender: 'male' | 'female'
  agility: number
  mental: number
  will: number
  strength: number
  armor: number
  /** Derivados de la configuración de atributos (solo lectura). */
  health: number
  total_attributes: number
  abilities?: HeroAbilityRef[]
}

/** Configuración de atributos de héroe (singleton). */
export interface HeroAttributesConfig {
  min_attribute_value: number
  max_attribute_value: number
  min_total_attributes: number
  max_total_attributes: number
  agility_multiplier: number
  mental_multiplier: number
  will_multiplier: number
  strength_multiplier: number
  armor_multiplier: number
  total_health_base: number
}

// --- Cartas (cluster card-cluster) ---

/** Opción del selector de tipos de carta (endpoint options: con flags). */
export interface CardTypeOption extends TaxonomyOption {
  hero_superclass_id: number | null
  /** Muestra el select de subtipo en el form de cartas. */
  allows_subtypes: boolean
  /** Muestra tipo de equipo y manos en el form de cartas. */
  is_equipment: boolean
}

/** Opción del selector de tipos de equipo (endpoint options: con flag). */
export interface EquipmentTypeOption extends TaxonomyOption {
  /** Las cartas de este tipo llevan manos (armas). */
  uses_hands: boolean
}

/** Opción del selector de subtipos de equipo (con su tipo, para acotar). */
export interface EquipmentSubtypeOption extends TaxonomyOption {
  equipment_type_id: number
}

/** Carta jugable (con slug y single). Renderizable a PNG (750x1050). */
export interface Card extends EntityBase {
  name: Translations
  lore_text: Translations
  epic_quote: Translations
  effect: Translations
  restriction: Translations
  faction_id: number | null
  card_type_id: number
  card_subtype_id: number | null
  equipment_type_id: number | null
  equipment_subtype_id: number | null
  attack_type: 'physical' | 'magical' | null
  attack_range_id: number | null
  attack_subtype_id: number | null
  hero_ability_id: number | null
  hands: number | null
  cost: string | null
  area: boolean
  is_unique: boolean
  faction?: FactionOption | null
  card_type?: CardTypeOption | null
  card_subtype?: TaxonomyOption | null
  equipment_type?: EquipmentTypeOption | null
  equipment_subtype?: TaxonomyOption | null
  attack_range?: TaxonomyOption | null
  attack_subtype?: TaxonomyOption | null
  hero_ability?: CardHeroAbilityRef | null
}

/**
 * Habilidad otorgada dentro de una carta (Resource del admin): completa,
 * para pintarla en el single con su tipado, coste y descripción.
 */
export interface CardHeroAbilityRef {
  id: number
  name: Translations
  description: Translations
  cost: string | null
  attack_type: 'physical' | 'magical' | null
  area: boolean
  attack_range?: TaxonomyOption | null
  attack_subtype?: TaxonomyOption | null
}

// --- Mazos de facción y su configuración (cluster deck-cluster) ---

/** Configuración de mazo por modo (sin soft delete: deleted_at siempre null). */
export interface DeckAttributesConfig extends EntityListItem {
  game_mode_id: number | null
  game_mode?: TaxonomyOption | null
  min_cards: number
  max_cards: number
  max_copies_per_card: number
  required_heroes: number
}

/** Carta dentro de un mazo (mínimo del Resource + copias del pivot). */
export interface DeckCardItem {
  id: number
  name: Translations
  cost: string | null
  image: string | null
  copies: number
}

/** Héroe dentro de un mazo (mínimo del Resource). */
export interface DeckHeroItem {
  id: number
  name: Translations
  image: string | null
}

export interface FactionDeck extends EntityBase {
  name: Translations
  description: Translations
  epic_quote: Translations
  game_mode_id: number | null
  game_mode?: TaxonomyOption | null
  factions?: FactionOption[]
  heroes?: DeckHeroItem[]
  cards?: DeckCardItem[]
  total_cards: number
  total_heroes: number
}

/** Error localizable de publicación de mazo (clave i18n del admin + params). */
export interface DeckPublishError {
  key: string
  params?: Record<string, string | number>
}

// --- Payloads de render a PNG (cluster render-pdf; RENDER-SPEC §4) ---
// Forma EXACTA de renderData(locale, type): textos YA localizados (strings,
// no mapas) y urls de iconos resueltas por el server (pueden ser null).

export type RenderIcons = Record<string, string | null>

/** Un dado del coste, ya parseado en servidor (parsed_cost de HasCost). */
export interface RenderDie {
  color: 'red' | 'green' | 'blue'
  letter: 'R' | 'G' | 'B'
}

export interface RenderFaction {
  name: string
  color: string | null
  text_is_dark: boolean
  icon_url: string | null
}

/** Tipo/rango/subtipo de ataque, localizados. */
export interface RenderAttack {
  type: string | null
  range: string | null
  subtype: string | null
}

export interface CardRenderData {
  name: string
  image: string | null
  icons: RenderIcons
  cost: string | null
  cost_parsed: RenderDie[]
  type: string | null
  subtype: string | null
  equipment_type: string | null
  equipment_subtype: string | null
  hands: number | null
  attack: RenderAttack | null
  area: boolean
  is_unique: boolean
  /** Habilidad de héroe otorgada (se pinta al pie de la caja de texto). */
  hero_ability?: HeroAbilityRenderData | null
  effect: string | null
  restriction: string | null
  lore_text: string | null
  epic_quote: string | null
  faction: RenderFaction | null
}

export interface HeroAbilityRenderData {
  name: string
  cost: string | null
  cost_parsed: RenderDie[]
  attack: RenderAttack | null
  area: boolean
  description: string | null
}

export interface HeroRenderData {
  name: string
  image: string | null
  icons: RenderIcons
  attributes: {
    agility: number
    mental: number
    will: number
    strength: number
    armor: number
  }
  health: number
  gender: 'male' | 'female'
  /** Nombres YA localizados y con el género del héroe aplicado. */
  race: string | null
  class: string | null
  superclass: string | null
  /** Pasiva de la clase (nombre = clase con género), antes que la propia. */
  class_passive: { name: string | null; description: string | null } | null
  passive: { name: string | null; description: string | null } | null
  abilities: HeroAbilityRenderData[]
  faction: RenderFaction | null
  lore_text: string | null
  epic_quote: string | null
}

export interface CounterRenderData {
  name: string
  image: string | null
  icons: RenderIcons
  type: CounterType
  effect: string | null
}

// Payloads de facción y mazo (tarjetas CSS renderizables): forma EXACTA de
// renderData() en Faction/FactionDeck de la api. Aun así los componentes
// programan defensivo: cualquier campo puede venir null o faltar.

export interface FactionRenderData {
  id?: number
  name: string | null
  color: string | null
  text_is_dark: boolean | null
  icon: string | null
  lore_text: string | null
  epic_quote: string | null
  /** Totales de contenido PUBLICADO (como la web pública). */
  heroes_count?: number | null
  cards_count?: number | null
  decks_count?: number | null
}

/** Facción de un mazo en el render (nombre localizado + su color). */
export interface DeckRenderFaction {
  name: string | null
  color: string | null
  text_is_dark?: boolean | null
}

/** Carta de un mazo en el render (nombre localizado + copias). */
export interface DeckRenderCard {
  name: string | null
  copies: number | null
}

export interface FactionDeckRenderData {
  id?: number
  name: string | null
  icon: string | null
  /** Nombre del modo YA localizado. */
  game_mode?: string | null
  factions?: DeckRenderFaction[] | null
  /** Totales de contenido publicado (las cartas suman copias). */
  total_heroes?: number | null
  total_cards?: number | null
  cards?: DeckRenderCard[] | null
  /** Héroes del mazo (solo el nombre localizado). */
  heroes?: Array<{ name?: string | null }> | null
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}
