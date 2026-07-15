// Segmentos de URL de las herramientas públicas por locale: deben casar con
// la canónica de la vista (misma mecánica que las Descargas). La página
// "Herramientas" en sí es del CRM (la crea el usuario con el editor y un CTA
// hacia la herramienta); aquí solo vive la ruta de cada herramienta:
// /es/herramientas/contador-de-vidas y /en/tools/life-counter (eu queda
// preparado hasta que se active el locale — el router solo enruta es/en).
export const TOOLS_PATHS: Record<string, string> = {
  es: 'herramientas',
  eu: 'tresnak',
  en: 'tools',
}

export const LIFE_COUNTER_PATHS: Record<string, string> = {
  es: 'contador-de-vidas',
  eu: 'bizitza-kontagailua',
  en: 'life-counter',
}

export function toolsPattern(): string {
  return [...new Set(Object.values(TOOLS_PATHS))].join('|')
}

export function lifeCounterPattern(): string {
  return [...new Set(Object.values(LIFE_COUNTER_PATHS))].join('|')
}
