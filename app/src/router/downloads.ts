// Segmentos de URL del apartado público de Descargas por locale (doc 10):
// deben casar con la nav y con la canónica de la vista.
export const DOWNLOAD_PATHS: Record<string, string> = {
  es: 'descargas',
  eu: 'deskargak',
  en: 'downloads',
}

export function downloadsPattern(): string {
  return [...new Set(Object.values(DOWNLOAD_PATHS))].join('|')
}
