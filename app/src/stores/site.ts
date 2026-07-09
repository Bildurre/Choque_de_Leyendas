import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/lib/api'
import { useLocalesStore } from '@/stores/locales'

// Configuración de la web (doc 10, GET /api/site): título, logo, favicon,
// fuentes y color de acento — fijo o ALEATORIO estilo CDL: se sortea uno de
// los candidatos al cargar la página y se re-sortea al navegar (la SPA no
// recarga, así que el disparador extra es router.afterEach → onNavigate()).
export interface SiteFont {
  label: string
  stack: string
  /** Ficheros @font-face (vacío en las pilas del sistema). */
  files: { family: string; src: string; weight: string; style: string }[]
}

export interface SiteSettings {
  title: Record<string, string>
  description: Record<string, string>
  logo: Record<string, string>
  favicon: string | null
  accent_mode: 'fixed' | 'random'
  accent_color: string
  accent_colors: string[]
  font_headings: string
  font_body: string
  font_special: string
  footer_text: Record<string, string>
  fonts: Record<string, SiteFont>
  /** SVG de cada logo inlineado por la API (currentColor hereda el acento). */
  logo_inline: Record<string, string>
}

/** CSS @font-face de un catálogo de fuentes (los navegadores solo descargan las usadas). */
export function fontFacesCss(fonts: Record<string, SiteFont>): string {
  return Object.values(fonts)
    .flatMap((font) => font.files)
    .map(
      (file) =>
        `@font-face { font-family: '${file.family}'; src: url('${file.src}'); ` +
        `font-weight: ${file.weight}; font-style: ${file.style}; font-display: swap; }`,
    )
    .join('\n')
}

export const useSiteStore = defineStore('site', () => {
  const locales = useLocalesStore()
  const settings = ref<SiteSettings | null>(null)
  const currentAccent = ref<string | null>(null)

  const title = computed(() => {
    const map = settings.value?.title ?? {}
    return map[locales.current] || map[locales.defaultLocale] || Object.values(map)[0] || ''
  })

  const footerText = computed(() => {
    const map = settings.value?.footer_text ?? {}
    return map[locales.current] || map[locales.defaultLocale] || Object.values(map)[0] || ''
  })

  const description = computed(() => {
    const map = settings.value?.description ?? {}
    return map[locales.current] || map[locales.defaultLocale] || Object.values(map)[0] || ''
  })

  // Logo del idioma actual (con fallback al por defecto, como el título).
  const logoUrl = computed(() => {
    const map = settings.value?.logo ?? {}
    return map[locales.current] || map[locales.defaultLocale] || Object.values(map)[0] || null
  })

  const logoInline = computed(() => {
    const map = settings.value?.logo_inline ?? {}
    const url = logoUrl.value
    // El inline correspondiente a la URL resuelta (no mezclar idiomas).
    const key = Object.entries(settings.value?.logo ?? {}).find(([, u]) => u === url)?.[0]
    return (key && map[key]) || null
  })

  /** Título del documento: "página · sitio" (o solo una de las partes). */
  function documentTitle(pageTitle?: string): string {
    return [pageTitle, title.value].filter(Boolean).join(' · ')
  }

  /** Deriva los tonos del acento a partir de un HEX (via color-mix del navegador). */
  function applyAccent(hex: string) {
    currentAccent.value = hex
    const root = document.documentElement.style
    root.setProperty('--accent-200', `color-mix(in srgb, ${hex} 30%, white)`)
    root.setProperty('--accent-300', `color-mix(in srgb, ${hex} 55%, white)`)
    root.setProperty('--accent-400', `color-mix(in srgb, ${hex} 80%, white)`)
    root.setProperty('--accent-500', hex)
    root.setProperty('--accent-600', `color-mix(in srgb, ${hex} 85%, black)`)
    root.setProperty('--accent-700', `color-mix(in srgb, ${hex} 70%, black)`)
  }

  /** Sortea un acento de los candidatos (evitando repetir el actual si hay más). */
  function pickAccent() {
    const pool = settings.value?.accent_colors ?? []
    if (!pool.length) return
    const candidates = pool.length > 1 ? pool.filter((c) => c !== currentAccent.value) : pool
    applyAccent(candidates[Math.floor(Math.random() * candidates.length)])
  }

  function applyFonts() {
    if (!settings.value) return
    const fonts = settings.value.fonts ?? {}

    // @font-face de todo el catálogo (solo se descargan las que se usan).
    let style = document.getElementById('site-fonts')
    if (!style) {
      style = document.createElement('style')
      style.id = 'site-fonts'
      document.head.appendChild(style)
    }
    style.textContent = fontFacesCss(fonts)

    const root = document.documentElement.style
    root.setProperty('--font-headings', fonts[settings.value.font_headings]?.stack || 'inherit')
    root.setProperty('--font-body', fonts[settings.value.font_body]?.stack || '')
    root.setProperty('--font-special', fonts[settings.value.font_special]?.stack || 'inherit')
  }

  function applyFavicon() {
    if (!settings.value?.favicon) return
    let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
    if (!link) {
      link = document.createElement('link')
      link.rel = 'icon'
      document.head.appendChild(link)
    }
    link.href = settings.value.favicon
  }

  let inflight: Promise<void> | null = null

  /** Carga la configuración y la aplica (fuentes, favicon, acento). */
  function load(): Promise<void> {
    if (settings.value) return Promise.resolve()
    inflight ??= api
      .get('/site')
      .then(({ data }) => {
        settings.value = data.data
        applyFonts()
        applyFavicon()
        if (settings.value?.accent_mode === 'random') pickAccent()
        else if (settings.value) applyAccent(settings.value.accent_color)
        if (!document.title) document.title = documentTitle()
      })
      .catch(() => {
        // sin configuración: la web funciona con los defaults del tema
      })
      .finally(() => {
        inflight = null
      })
    return inflight
  }

  /** Disparador extra del modo aleatorio: re-sortea al navegar por la SPA. */
  function onNavigate() {
    if (settings.value?.accent_mode === 'random') pickAccent()
  }

  return {
    settings,
    title,
    footerText,
    description,
    logoUrl,
    logoInline,
    documentTitle,
    load,
    onNavigate,
  }
})
