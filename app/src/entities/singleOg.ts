// Open Graph de los singles (cluster app-singles). El mecanismo del motor
// (useHead) cubre title/description/canonical/alternates y lo aplica
// EntityDetailView; aquí se añaden las etiquetas og:* espejando ese head ya
// resuelto (el prerender captura cada ruta con carga fresca, así que los
// scrapers ven las og correctas por URL). Se reponen enteras en cada llamada.

export interface OgInput {
  /** Imagen social de la entidad (imagen de fondo o preview PNG). */
  image?: string | null
  /** og:type (article por defecto; profile para facciones, como el viejo). */
  type?: string
}

export function applyOgMeta(input: OgInput = {}): void {
  const head = document.head
  head.querySelectorAll('meta[property^="og:"]').forEach((tag) => tag.remove())

  const description = head.querySelector<HTMLMetaElement>('meta[name="description"]')?.content
  const canonical = head.querySelector<HTMLLinkElement>('link[rel="canonical"]')?.href

  const entries: Array<[string, string | null | undefined]> = [
    ['og:title', document.title],
    ['og:description', description],
    ['og:url', canonical],
    ['og:type', input.type ?? 'article'],
    ['og:image', input.image],
  ]

  for (const [property, content] of entries) {
    if (!content) continue
    const tag = document.createElement('meta')
    tag.setAttribute('property', property)
    tag.setAttribute('content', content)
    head.appendChild(tag)
  }
}
