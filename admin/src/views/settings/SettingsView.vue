<script setup lang="ts">
import { computed, onMounted, ref, watchEffect } from 'vue'
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
const logo = ref<Record<string, string>>({})
const favicon = ref<string | null>(null)
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

async function upload(file: File, replaces?: string | null): Promise<string | null> {
  try {
    const form = new FormData()
    form.append('image', file)
    // El backend borra el fichero sustituido: sin huérfanos.
    if (replaces) form.append('replaces', replaces)
    const { data } = await api.post('/admin/content/uploads', form)
    return data.url
  } catch {
    toast.danger(t('common.errors.action'))
    return null
  }
}

/** Borra la subida del disco (el botón "quitar"); en silencio si falla. */
async function removeUpload(url: string): Promise<void> {
  await api.delete('/admin/content/uploads', { data: { url } }).catch(() => {})
}

// Subida para TranslatableImage (una URL por idioma): lanza si falla para
// que el componente no borre el valor del locale activo.
async function uploadLogo(file: File, replaces?: string | null): Promise<string> {
  const url = await upload(file, replaces)
  if (!url) throw new Error('upload failed')
  return url
}

async function uploadFavicon(file: File | null) {
  if (!file) {
    if (favicon.value) removeUpload(favicon.value)
    favicon.value = null
    return
  }
  favicon.value = (await upload(file, favicon.value)) ?? favicon.value
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/settings/site')
    const s = data.data
    title.value = s.title ?? {}
    description.value = s.description ?? {}
    logo.value = s.logo ?? {}
    favicon.value = s.favicon
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
    await api.put('/admin/settings/site', {
      title: title.value,
      description: description.value,
      logo: logo.value,
      favicon: favicon.value,
      accent_mode: accentMode.value,
      accent_color: accentColor.value,
      accent_colors: accentColors.value,
      font_headings: fontHeadings.value,
      font_body: fontBody.value,
      font_special: fontSpecial.value,
      custom_fonts: customFonts.value.map(({ key, name, file }) => ({ key, name, file })),
      footer_text: footerText.value,
    })
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
            <!-- Logo por idioma (fallback al por defecto en la web) -->
            <TranslatableImage
              v-model="logo"
              :locales="locales.locales"
              :label="t('settings.fields.logo')"
              :upload="uploadLogo"
              :remove-file="removeUpload"
            />
            <ImageUpload
              :model-value="null"
              :current-url="favicon"
              :label="t('settings.fields.favicon')"
              accept=".png,.svg"
              :drag-text="t('common.imageDrag')"
              :hint-text="t('settings.fields.faviconHint')"
              @update:model-value="uploadFavicon"
              @remove="uploadFavicon(null)"
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
