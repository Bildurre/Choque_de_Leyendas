// URL del panel de administración (VITE_ADMIN_URL, patrón kontuan; fallback
// al puerto del admin en dev) y construcción de enlaces a los singles del
// admin de CdL para editores/administradores logueados en la web.
export const adminUrl =
  (import.meta.env.VITE_ADMIN_URL as string | undefined) || 'http://localhost:5174'

// Segmentos del single de cada sección EN EL ADMIN, por locale: deben casar
// con routes.* de los i18n del admin (i18n-paths.ts registra además cada
// segmento de los otros locales como alias, así que cualquier par
// segmento/slug coherente resuelve). Claves = key del registry de la app.
const ADMIN_SEGMENTS: Record<string, Record<string, string>> = {
  cards: { es: 'cartas', en: 'cards' },
  heroes: { es: 'heroes', en: 'heroes' },
  factions: { es: 'facciones', en: 'factions' },
  decks: { es: 'mazos-de-faccion', en: 'faction-decks' },
}

/** URL absoluta del single de una entidad en el admin (null si no aplica). */
export function adminEntityUrl(
  sectionKey: string,
  slug: string | null | undefined,
  locale: string,
): string | null {
  const segments = ADMIN_SEGMENTS[sectionKey]
  if (!segments || !slug) return null
  const segment = segments[locale] ?? Object.values(segments)[0]
  return `${adminUrl}/${segment}/${slug}`
}
