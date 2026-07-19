<script setup lang="ts">
import { computed, onMounted, ref, watch, watchEffect } from 'vue'
import { useI18n } from 'vue-i18n'
import { Plus, Save, Upload, X } from '@lucide/vue'
import {
  BaseButton,
  BaseInput,
  BaseSelect,
  FontUpload,
  ImageUpload,
  TranslatableImage,
  PaletteColorPicker,
  TranslatableInput,
  useToast,
} from '@edc-motor/ui'
import { api } from '@/lib/api'
import { useEditorLabels } from '@/lib/editorLabels'
import { useLocalesStore } from '@/stores/locales'

// Configuración de la web pública (doc 10): identidad (título, logo,
// favicon), apariencia (acento fijo o ALEATORIO estilo CDL, fuentes) y pie.
// La SPA pública la aplica al arrancar y re-sortea el acento al navegar.
const { t, te } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const richLabels = useEditorLabels()

const loading = ref(true)
const saving = ref(false)

const title = ref<Record<string, string>>({})
const description = ref<Record<string, string>>({})
// Logo traducible DIFERIDO (mismo patrón que TranslatableImage/ImageUpload):
// el mapa mezcla URLs guardadas (string) y ficheros pendientes (File); nada
// se sube hasta el GUARDAR. `originalLogo` guarda el snapshot cargado (solo
// URLs) para poder sustituir (`replaces`) o borrar del disco lo que cambie.
const logo = ref<Record<string, string | File>>({})
const originalLogo = ref<Record<string, string>>({})
// Favicon DIFERIDO con el mismo patrón que un ImageUpload de entidad:
// `currentFavicon` es lo que se muestra, `faviconFile`/`removeFavicon` viajan
// al guardar, `originalFavicon` es el snapshot para sustituir/borrar.
const faviconFile = ref<File | null>(null)
const currentFavicon = ref<string | null>(null)
const originalFavicon = ref<string | null>(null)
const removeFavicon = ref(false)
watch(faviconFile, (file) => {
  if (file) removeFavicon.value = false
})
const accentMode = ref<'fixed' | 'random'>('fixed')
const accentColor = ref('#6c5ce7')
const accentColors = ref<string[]>([])
const fontHeadings = ref('system')
const fontBody = ref('system')
const fontSpecial = ref('system')
const footerText = ref<Record<string, string>>({})

interface SiteFont {
  label: string
  stack: string
  files: { family: string; src: string; weight: string; style: string }[]
}
interface CustomFont {
  key: string
  name: string
  file: string
}
const fonts = ref<Record<string, SiteFont>>({})
const customFonts = ref<CustomFont[]>([])

const fontOptions = computed(() =>
  Object.entries(fonts.value).map(([key, font]) => ({
    value: key,
    label: te(`settings.fonts.${key}`) ? t(`settings.fonts.${key}`) : font.label,
  })),
)

// @font-face del catálogo, también aquí: así las vistas previas del select
// se pintan con la fuente real (los ficheros llegan con CORS del API).
watchEffect(() => {
  let style = document.getElementById('site-fonts-preview')
  if (!style) {
    style = document.createElement('style')
    style.id = 'site-fonts-preview'
    document.head.appendChild(style)
  }
  style.textContent = Object.values(fonts.value)
    .flatMap((font) => font.files)
    .map(
      (file) =>
        `@font-face { font-family: '${file.family}'; src: url('${file.src}'); ` +
        `font-weight: ${file.weight}; font-style: ${file.style}; font-display: swap; }`,
    )
    .join('\n')
})

/** Candidato del modo aleatorio elegido en el picker (se añade a la lista). */
const candidate = ref('#22c55e')

function addColor() {
  if (!accentColors.value.includes(candidate.value)) accentColors.value.push(candidate.value)
}

function removeColor(index: number) {
  accentColors.value.splice(index, 1)
}

// --- Fuentes propias (font uploader) ---
const fontName = ref('')
const fontFile = ref<File | null>(null)
const uploadingFont = ref(false)

async function uploadFont() {
  if (!fontName.value.trim() || !fontFile.value) return
  uploadingFont.value = true
  try {
    const form = new FormData()
    form.append('name', fontName.value.trim())
    form.append('file', fontFile.value)
    const { data } = await api.post('/admin/settings/fonts', form)
    const font = data.data as CustomFont & { url: string }
    customFonts.value = [...customFonts.value.filter((f) => f.key !== font.key), font]
    // Disponible al momento en los selects y su vista previa.
    fonts.value = {
      ...fonts.value,
      [font.key]: {
        label: font.name,
        stack: `'${font.name}', system-ui, sans-serif`,
        files: [{ family: font.name, src: font.url, weight: '100 900', style: 'normal' }],
      },
    }
    fontName.value = ''
    fontFile.value = null
  } catch {
    toast.danger(t('common.errors.action'))
  } finally {
    uploadingFont.value = false
  }
}

function removeCustomFont(font: CustomFont) {
  customFonts.value = customFonts.value.filter((f) => f.key !== font.key)
  fonts.value = Object.fromEntries(Object.entries(fonts.value).filter(([key]) => key !== font.key))
  if (fontHeadings.value === font.key) fontHeadings.value = 'system'
  if (fontBody.value === font.key) fontBody.value = 'system'
}

/** Sube un fichero al endpoint de contenidos (misma ruta que las de los
 *  bloques); el backend borra el sustituido (`replaces`): sin huérfanos.
 *  Se llama SOLO al guardar (patrón diferido): nunca al elegir el fichero. */
async function upload(file: File, replaces?: string | null): Promise<string> {
  const form = new FormData()
  form.append('image', file)
  if (replaces) form.append('replaces', replaces)
  const { data } = await api.post('/admin/content/uploads', form)
  return data.url
}

/** Borra la subida del disco (el botón "quitar", diferido al guardar); en
 *  silencio si falla (no debe tumbar el resto del guardado). */
async function removeUpload(url: string): Promise<void> {
  await api.delete('/admin/content/uploads', { data: { url } }).catch(() => {})
}

/** Resuelve el logo final al guardar: sube los ficheros pendientes por
 *  locale (sustituyendo el anterior), borra los que se hayan quitado y deja
 *  igual los que no cambiaron. Nada de esto viaja hasta el submit. */
async function resolveLogo(): Promise<Record<string, string>> {
  const codes = new Set([...Object.keys(originalLogo.value), ...Object.keys(logo.value)])
  const result: Record<string, string> = {}
  for (const code of codes) {
    const original = originalLogo.value[code] ?? null
    const current = logo.value[code]
    if (current instanceof File) {
      result[code] = await upload(current, original)
    } else if (typeof current === 'string') {
      result[code] = current
    } else if (original) {
      await removeUpload(original)
    }
  }
  return result
}

/** Resuelve el favicon final al guardar: sube el pendiente (sustituyendo el
 *  anterior), lo borra si se quitó, o lo deja igual. Diferido hasta el submit. */
async function resolveFavicon(): Promise<string | null> {
  if (faviconFile.value) {
    return await upload(faviconFile.value, originalFavicon.value)
  }
  if (removeFavicon.value) {
    if (originalFavicon.value) await removeUpload(originalFavicon.value)
    return null
  }
  return originalFavicon.value
}

function onRemoveFavicon() {
  removeFavicon.value = true
  currentFavicon.value = null
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/settings/site')
    const s = data.data
    title.value = s.title ?? {}
    description.value = s.description ?? {}
    logo.value = { ...(s.logo ?? {}) }
    originalLogo.value = { ...(s.logo ?? {}) }
    faviconFile.value = null
    currentFavicon.value = s.favicon ?? null
    originalFavicon.value = s.favicon ?? null
    removeFavicon.value = false
    accentMode.value = s.accent_mode
    accentColor.value = s.accent_color
    accentColors.value = s.accent_colors ?? []
    fontHeadings.value = s.font_headings
    fontBody.value = s.font_body
    fontSpecial.value = s.font_special ?? 'system'
    footerText.value = s.footer_text ?? {}
    fonts.value = s.fonts ?? {}
    customFonts.value = s.custom_fonts ?? []
  } catch {
    toast.danger(t('common.errors.load'))
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    // Logo y favicon se suben/borran aquí, solo al guardar (patrón diferido).
    const [resolvedLogo, resolvedFavicon] = await Promise.all([resolveLogo(), resolveFavicon()])
    await api.put('/admin/settings/site', {
      title: title.value,
      description: description.value,
      logo: resolvedLogo,
      favicon: resolvedFavicon,
      accent_mode: accentMode.value,
      accent_color: accentColor.value,
      accent_colors: accentColors.value,
      font_headings: fontHeadings.value,
      font_body: fontBody.value,
      font_special: fontSpecial.value,
      custom_fonts: customFonts.value.map(({ key, name, file }) => ({ key, name, file })),
      footer_text: footerText.value,
    })
    // La vista no se cierra tras guardar: el nuevo estado persistido pasa a
    // ser el snapshot base para el próximo guardado.
    logo.value = { ...resolvedLogo }
    originalLogo.value = { ...resolvedLogo }
    faviconFile.value = null
    currentFavicon.value = resolvedFavicon
    originalFavicon.value = resolvedFavicon
    removeFavicon.value = false
    toast.success(t('settings.toast.saved'))
  } catch {
    toast.danger(t('settings.toast.saveError'))
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await locales.load()
  await load()
})
</script>

<template>
  <div v-if="!loading" class="settings-view">
    <div class="list-view__top">
      <BaseButton :disabled="saving" @click="save">
        <template #icon><Save :size="16" /></template>
        {{ t('common.save') }}
      </BaseButton>
    </div>

    <!-- Dos columnas explícitas (masonry determinista): cada columna apila
         sus tarjetas pegadas, sin filas alineadas por alturas -->
    <div class="settings-view__columns">
      <div class="settings-view__col">
        <!-- Identidad -->
        <section class="settings-view__section">
          <h2>{{ t('settings.sections.identity') }}</h2>
          <TranslatableInput
            v-model="title"
            :locales="locales.locales"
            :label="t('settings.fields.title')"
          />
          <TranslatableInput
            v-model="description"
            :locales="locales.locales"
            :label="t('settings.fields.description')"
            type="textarea"
            :rows="2"
          />
          <div class="settings-view__uploads">
            <!-- Logo por idioma (fallback al por defecto en la web): DIFERIDO,
                 la subida real no viaja hasta el guardar. -->
            <TranslatableImage
              v-model="logo"
              :locales="locales.locales"
              :label="t('settings.fields.logo')"
            />
            <ImageUpload
              v-model="faviconFile"
              :current-url="currentFavicon"
              :label="t('settings.fields.favicon')"
              accept=".png,.svg"
              :drag-text="t('common.imageDrag')"
              :hint-text="t('settings.fields.faviconHint')"
              @remove="onRemoveFavicon"
            />
          </div>
        </section>

        <!-- Pie -->
        <section class="settings-view__section">
          <h2>{{ t('settings.sections.footer') }}</h2>
          <TranslatableInput
            v-model="footerText"
            :locales="locales.locales"
            :label="t('settings.fields.footerText')"
            type="wysiwyg"
            :rich-labels="richLabels"
          />
        </section>
      </div>

      <div class="settings-view__col">
        <!-- Apariencia -->
        <section class="settings-view__section">
          <h2>{{ t('settings.sections.appearance') }}</h2>
          <BaseSelect
            v-model="accentMode"
            :label="t('settings.fields.accentMode')"
            :options="[
              { value: 'fixed', label: t('settings.accentModes.fixed') },
              { value: 'random', label: t('settings.accentModes.random') },
            ]"
          />

          <PaletteColorPicker
            v-if="accentMode === 'fixed'"
            v-model="accentColor"
            :label="t('settings.fields.accentColor')"
          />

          <template v-else>
            <p class="settings-view__hint">{{ t('settings.fields.accentColorsHint') }}</p>
            <!-- Candidatos como etiquetas en fila (con wrap) -->
            <ul v-if="accentColors.length" class="settings-view__colors">
              <li v-for="(color, index) in accentColors" :key="color">
                <span class="settings-view__swatch" :style="{ background: color }" />
                <code>{{ color }}</code>
                <button
                  type="button"
                  class="settings-view__chip-remove"
                  :title="t('common.actions.delete')"
                  @click="removeColor(index)"
                >
                  <X :size="12" />
                </button>
              </li>
            </ul>
            <div class="settings-view__add-color">
              <PaletteColorPicker v-model="candidate" :label="t('settings.fields.accentColors')" />
              <BaseButton variant="text" @click="addColor">
                <template #icon><Plus :size="14" /></template>
                {{ t('settings.addColor') }}
              </BaseButton>
            </div>
          </template>

          <div class="settings-view__fonts">
            <div>
              <BaseSelect
                v-model="fontHeadings"
                :label="t('settings.fields.fontHeadings')"
                :options="fontOptions"
              />
              <p
                class="settings-view__font-preview"
                :style="{ fontFamily: fonts[fontHeadings]?.stack }"
              >
                {{ t('settings.fontPreviewHeading') }}
              </p>
            </div>
            <div>
              <BaseSelect
                v-model="fontBody"
                :label="t('settings.fields.fontBody')"
                :options="fontOptions"
              />
              <p
                class="settings-view__font-preview"
                :style="{ fontFamily: fonts[fontBody]?.stack }"
              >
                {{ t('settings.fontPreviewBody') }}
              </p>
            </div>
            <!-- Fuente "especial": acentos puntuales (hoy, el bloque cita) -->
            <div>
              <BaseSelect
                v-model="fontSpecial"
                :label="t('settings.fields.fontSpecial')"
                :options="fontOptions"
              />
              <p
                class="settings-view__font-preview"
                :style="{ fontFamily: fonts[fontSpecial]?.stack }"
              >
                {{ t('settings.fontPreviewSpecial') }}
              </p>
            </div>
          </div>

          <!-- Fuentes propias: subir un fichero la hace elegible arriba -->
          <div class="settings-view__custom-fonts">
            <span class="form-field__label">{{ t('settings.fields.customFonts') }}</span>
            <ul v-if="customFonts.length" class="settings-view__colors">
              <li v-for="font in customFonts" :key="font.key">
                <code>{{ font.name }}</code>
                <button
                  type="button"
                  class="settings-view__chip-remove"
                  :title="t('common.actions.delete')"
                  @click="removeCustomFont(font)"
                >
                  <X :size="12" />
                </button>
              </li>
            </ul>
            <div class="settings-view__font-upload">
              <BaseInput v-model="fontName" :label="t('settings.fields.fontName')" />
              <FontUpload
                v-model="fontFile"
                :drag-text="t('settings.fields.fontDrag')"
                :hint-text="t('settings.fields.fontFileHint')"
                :too-large-text="t('common.fileTooLarge')"
                :invalid-type-text="t('common.fileType')"
              />
              <BaseButton
                variant="text"
                :disabled="uploadingFont || !fontName.trim() || !fontFile"
                @click="uploadFont"
              >
                <template #icon><Upload :size="14" /></template>
                {{ t('settings.uploadFont') }}
              </BaseButton>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
