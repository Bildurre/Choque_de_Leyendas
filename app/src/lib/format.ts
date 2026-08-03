// Formato compartido de las cards de PDF (descargas públicas y colección):
// tamaño legible y fecha corta en el locale activo.
export function formatSize(bytes: number | null): string {
  if (!bytes) return ''
  if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  return `${(bytes / 1024).toFixed(0)} KB`
}

export function formatDate(value: string | null, locale: string): string {
  if (!value) return ''
  return new Date(value).toLocaleDateString(locale, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

// «Ver»: el mismo endpoint de descarga con ?inline=1 (Content-Disposition
// inline): el navegador abre el PDF en la pestaña en vez de descargarlo.
export function inlineUrl(url: string): string {
  return `${url}${url.includes('?') ? '&' : '?'}inline=1`
}
