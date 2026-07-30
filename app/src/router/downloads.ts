// Slug por locale de la PÁGINA del CRM de Descargas (con el bloque
// «Descargas»): ya no hay ruta dedicada — el header y el panel de usuario
// enlazan a { name: 'page', params: { slug: DOWNLOAD_PATHS[locale] } }. Debe
// casar con los slugs de esa página en el CRM.
export const DOWNLOAD_PATHS: Record<string, string> = {
  es: 'descargas',
  eu: 'deskargak',
  en: 'downloads',
}
