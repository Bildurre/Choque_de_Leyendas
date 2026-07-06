// Prerender de las rutas públicas (doc 10, DC-18): tras `vite build`, sirve
// dist/ con `vite preview`, pide el sitemap a la API (que debe estar
// levantada), renderiza cada URL con Chromium y guarda el HTML resultante en
// dist/<ruta>/index.html. El hosting estático sirve esos ficheros como HTML
// inicial indexable; la SPA se hidrata al cargar.
//
// Uso:  npm run build && npm run prerender
// Env:  VITE_API_URL (la API, por defecto http://localhost:8010)
//       MOTOR_CHROME_PATH (binario de Chromium para puppeteer-core)

import { spawn } from 'node:child_process'
import { appendFile, mkdir, writeFile } from 'node:fs/promises'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import puppeteer from 'puppeteer-core'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const apiUrl = process.env.VITE_API_URL || 'http://localhost:8010'
const port = Number(process.env.PRERENDER_PORT || 4173)
const chromePath = process.env.MOTOR_CHROME_PATH || process.env.CHROME_PATH || '/usr/bin/chromium'

async function paths() {
  const res = await fetch(`${apiUrl}/sitemap.xml`)
  if (!res.ok) throw new Error(`sitemap: HTTP ${res.status}`)
  const xml = await res.text()
  const locs = [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => new URL(m[1]).pathname)
  return [...new Set(locs)]
}

function preview() {
  return new Promise((resolve, reject) => {
    // detached: el kill del final tumba el grupo entero (npx + vite).
    const child = spawn('npx', ['vite', 'preview', '--port', String(port), '--strictPort'], {
      cwd: root,
      stdio: ['ignore', 'pipe', 'inherit'],
      detached: true,
    })
    child.stdout.on('data', (chunk) => {
      if (String(chunk).includes('http')) resolve(child)
    })
    child.on('error', reject)
    setTimeout(() => resolve(child), 5000)
  })
}

const routes = await paths()
console.log(`prerender: ${routes.length} rutas del sitemap`)

const server = await preview()
const browser = await puppeteer.launch({ executablePath: chromePath, args: ['--no-sandbox'] })

let failed = 0
try {
  const page = await browser.newPage()
  for (const route of routes) {
    try {
      await page.goto(`http://localhost:${port}${route}`, {
        waitUntil: 'networkidle0',
        timeout: 30000,
      })
      const html = `<!DOCTYPE html>\n${await page.evaluate(
        () => document.documentElement.outerHTML,
      )}`
      const file = join(root, 'dist', route.replace(/^\//, ''), 'index.html')
      await mkdir(dirname(file), { recursive: true })
      await writeFile(file, html)
      console.log(`  ✓ ${route}`)
    } catch (error) {
      failed++
      console.error(`  ✗ ${route}: ${error.message}`)
    }
  }
} finally {
  await browser.close()
  try {
    process.kill(-server.pid) // el grupo entero (npx + vite preview)
  } catch {
    server.kill()
  }
}

// robots.txt: la URL absoluta del sitemap (SEO, doc 10).
await appendFile(join(root, 'dist', 'robots.txt'), `\nSitemap: ${apiUrl}/sitemap.xml\n`)

console.log(failed ? `prerender: ${failed} rutas fallidas` : 'prerender: todo generado')
process.exit(failed ? 1 : 0)
