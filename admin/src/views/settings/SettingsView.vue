<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch, watchEffect } from 'vue'
import { useI18n } from 'vue-i18n'
import { Plus, SquarePen, Upload, X } from '@lucide/vue'
import {
  BaseButton,
  BaseInput,
  BaseSelect,
  EditModal,
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
import InfoBlock from '@/components/InfoBlock.vue'

// Configuración de la web pública (doc 10), al patrón de la configuración de
// atributos: CADA sección es una card de LECTURA (info-list clave→valor, con
// miniaturas para las imágenes) con botón Editar que abre un modal SOLO con
// los campos de esa sección. El PUT del settings es de payload completo: al
// guardar viaja la sección editada mezclada sobre el estado persistido. Las
// imágenes siguen DIFERIDAS: el fichero elegido se sube al guardar el modal.
const { t, te } = useI18n()
const toast = useToast()
const locales = useLocalesStore()
const richLabels = useEditorLabels()

const loading = ref(true)
const saving = ref(false)

// --- Estado PERSISTIDO (lo que muestran las cards de lectura) ---
const title = ref<Record<string, string>>({})
const description = ref<Record<string, string>>({})
// Logo por idioma y favicon: aquí solo URLs guardadas; los File pendientes
// viven en el formulario del modal (patrón diferido).
const logo = ref<Record<string, string>>({})
const favicon = ref<string | null>(null)
// Fondos de la web pública (herramientas y errores; los índices se componen
// desde el CRM y toman el fondo de su propia página). Las claves casan con
// `index_backgrounds` del site settings del motor; el valor de cada entrada
// es su clave i18n (settings.indexBackgrounds.*).
const INDEX_BACKGROUND_KEYS = {
  'life-counter': 'lifeCounter',
  'dice-roller': 'diceRoller',
  errors: 'errors',
} as const
const indexBackgrounds = ref<Record<string, string | null>>({})
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

// --- Modal de edición por sección (una a la vez) ---
type Section = 'identity' | 'appearance' | 'footer' | 'indexBackgrounds'
const editing = ref<Section | null>(null)

// El formulario del modal parte de una COPIA de la sección: cancelar no toca
// el estado persistido. Los mapas de imagen mezclan URLs guardadas (string)
// y ficheros pendientes (File); nada se sube hasta el GUARDAR del modal.
const form = reactive({
  title: {} as Record<string, string>,
  description: {} as Record<string, string>,
  logo: {} as Record<string, string | File>,
  faviconFile: null as File | null,
  faviconCurrent: null as string | null,
  removeFavicon: false,
  indexBackgrounds: {} as Record<string, string | File | null>,
  accentMode: 'fixed' as 'fixed' | 'random',
  accentColor: '#6c5ce7',
  accentColors: [] as string[],
  fontHeadings: 'system',
  fontBody: 'system',
  fontSpecial: 'system',
  customFonts: [] as CustomFont[],
  footerText: {} as Record<string, string>,
})
watch(
  () => form.faviconFile,
  (file) => {
    if (file) form.removeFavicon = false
  },
)

const modalOpen = computed({
  get: () => editing.value !== null,
  set: (open: boolean) => {
    if (!open) editing.value = null
  },
})
const modalTitle = computed(() => (editing.value ? t(`settings.sections.${editing.value}`) : ''))
// Apariencia y fondos llevan muchos campos: modal ancho.
const modalSize = computed(() =>
  editing.value === 'appearance' || editing.value === 'indexBackgrounds' ? 'wide' : 'normal',
)

function openEdit(section: Section) {
  form.title = { ...title.value }
  form.description = { ...description.value }
  form.logo = { ...logo.value }
  form.faviconFile = null
  form.faviconCurrent = favicon.value
  form.removeFavicon = false
  form.indexBackgrounds = { ...indexBackgrounds.value }
  form.accentMode = accentMode.value
  form.accentColor = accentColor.value
  form.accentColors = [...accentColors.value]
  form.fontHeadings = fontHeadings.value
  form.fontBody = fontBody.value
  form.fontSpecial = fontSpecial.value
  form.customFonts = [...customFonts.value]
  form.footerText = { ...footerText.value }
  candidate.value = '#22c55e'
  fontName.value = ''
  fontFile.value = null
  editing.value = section
}

const fontOptions = computed(() =>
  Object.entries(fonts.value).map(([key, font]) => ({
    value: key,
    label: fontLabel(key, font),
  })),
)

function fontLabel(key: string, font?: SiteFont): string {
  if (te(`settings.fonts.${key}`)) return t(`settings.fonts.${key}`)
  return (font ?? fonts.value[key])?.label ?? key
}

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

// --- Ayudas de LECTURA para las cards ---

/** Valor traducible a mostrar: idioma actual, por defecto o el primero. */
function tr(map: Record<string, string>): string {
  return (
    map[locales.current] || map[locales.defaultLocale] || Object.values(map).find((v) => !!v) || ''
  )
}

/** URL del logo a mostrar en la card (idioma actual con fallback). */
const logoUrl = computed(() => {
  const value = logo.value[locales.current] || logo.value[locales.defaultLocale]
  return value || Object.values(logo.value).find((v) => !!v) || null
})

/** Texto plano del pie (el campo es wysiwyg): para el extracto de la card. */
const footerExcerpt = computed(() => {
  const html = tr(footerText.value)
  if (!html) return ''
  return new DOMParser().parseFromString(html, 'text/html').body.textContent?.trim() ?? ''
})

const customFontNames = computed(() => customFonts.value.map((f) => f.name).join(', '))

/** Candidato del modo aleatorio elegido en el picker (se añade a la lista). */
const candidate = ref('#22c55e')

function addColor() {
  if (!form.accentColors.includes(candidate.value)) form.accentColors.push(candidate.value)
}

function removeColor(index: number) {
  form.accentColors.splice(index, 1)
}

// --- Fuentes propias (font uploader; la subida del FICHERO de fuente es
// inmediata como hasta ahora, pero la lista solo persiste al guardar) ---
const fontName = ref('')
const fontFile = ref<File | null>(null)
const uploadingFont = ref(false)

async function uploadFont() {
  if (!fontName.value.trim() || !fontFile.value) return
  uploadingFont.value = true
  try {
    const body = new FormData()
    body.append('name', fontName.value.trim())
    body.append('file', fontFile.value)
    const { data } = await api.post('/admin/settings/fonts', body)
    const font = data.data as CustomFont & { url: string }
    form.customFonts = [...form.customFonts.filter((f) => f.key !== font.key), font]
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
  form.customFonts = form.customFonts.filter((f) => f.key !== font.key)
  fonts.value = Object.fromEntries(Object.entries(fonts.value).filter(([key]) => key !== font.key))
  if (form.fontHeadings === font.key) form.fontHeadings = 'system'
  if (form.fontBody === font.key) form.fontBody = 'system'
  if (form.fontSpecial === font.key) form.fontSpecial = 'system'
}

/** Sube un fichero al endpoint de contenidos (misma ruta que las de los
 *  bloques); el backend borra el sustituido (`replaces`): sin huérfanos.
 *  Se llama SOLO al guardar (patrón diferido): nunca al elegir el fichero. */
async function upload(file: File, replaces?: string | null): Promise<string> {
  const body = new FormData()
  body.append('image', file)
  if (replaces) body.append('replaces', replaces)
  const { data } = await api.post('/admin/content/uploads', body)
  return data.url
}

/** Borra la subida del disco (el botón "quitar", diferido al guardar); en
 *  silencio si falla (no debe tumbar el resto del guardado). */
async function removeUpload(url: string): Promise<void> {
  await api.delete('/admin/content/uploads', { data: { url } }).catch(() => {})
}

/** Resuelve el logo final al guardar el modal de identidad: sube los
 *  ficheros pendientes por locale (sustituyendo el anterior), borra los que
 *  se hayan quitado y deja igual los que no cambiaron. */
async function resolveLogo(): Promise<Record<string, string>> {
  const codes = new Set([...Object.keys(logo.value), ...Object.keys(form.logo)])
  const result: Record<string, string> = {}
  for (const code of codes) {
    const original = logo.value[code] ?? null
    const current = form.logo[code]
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
  if (form.faviconFile) {
    return await upload(form.faviconFile, favicon.value)
  }
  if (form.removeFavicon) {
    if (favicon.value) await removeUpload(favicon.value)
    return null
  }
  return favicon.value
}

function onRemoveFavicon() {
  form.removeFavicon = true
  form.faviconCurrent = null
}

/** Resuelve los fondos al guardar (misma mecánica que el favicon, por
 *  clave): sube los ficheros pendientes (sustituyendo el anterior), borra
 *  del disco los que se hayan quitado y deja igual los que no cambiaron.
 *  Devuelve SIEMPRE el mapa completo (el merge del servidor es superficial:
 *  un mapa parcial pisaría el resto de claves); las claves que esta UI ya
 *  no edita (los antiguos fondos de índices) pasan intactas. */
async function resolveIndexBackgrounds(): Promise<Record<string, string | null>> {
  const result: Record<string, string | null> = { ...indexBackgrounds.value }
  for (const key of Object.keys(INDEX_BACKGROUND_KEYS)) {
    const original = indexBackgrounds.value[key] ?? null
    const current = form.indexBackgrounds[key] ?? null
    if (current instanceof File) {
      result[key] = await upload(current, original)
    } else if (typeof current === 'string') {
      result[key] = current
    } else {
      if (original) await removeUpload(original)
      result[key] = null
    }
  }
  return result
}

function onIndexBackgroundFile(key: string, file: File | null) {
  // El null del "quitar" llega por @remove; aquí solo interesan los File.
  if (file) form.indexBackgrounds[key] = file
}

function onRemoveIndexBackground(key: string) {
  form.indexBackgrounds[key] = null
}

/** El File pendiente de la clave (para el v-model del ImageUpload). */
function indexBackgroundFile(key: string): File | null {
  const value = form.indexBackgrounds[key]
  return value instanceof File ? value : null
}

/** La URL guardada de la clave (para el `current-url` del ImageUpload). */
function indexBackgroundUrl(key: string): string | null {
  const value = form.indexBackgrounds[key]
  return typeof value === 'string' ? value : null
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/settings/site')
    const s = data.data
    title.value = s.title ?? {}
    description.value = s.description ?? {}
    logo.value = { ...(s.logo ?? {}) }
    favicon.value = s.favicon ?? null
    indexBackgrounds.value = { ...(s.index_backgrounds ?? {}) }
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
  const section = editing.value
  if (!section) return
  saving.value = true
  try {
    // El PUT valida el settings ENTERO: parte del estado persistido y pisa
    // encima SOLO la sección editada (imágenes resueltas aquí: diferidas).
    const payload = {
      title: title.value,
      description: description.value,
      logo: logo.value as Record<string, string>,
      favicon: favicon.value,
      index_backgrounds: indexBackgrounds.value,
      accent_mode: accentMode.value,
      accent_color: accentColor.value,
      accent_colors: accentColors.value,
      font_headings: fontHeadings.value,
      font_body: fontBody.value,
      font_special: fontSpecial.value,
      custom_fonts: customFonts.value.map(({ key, name, file }) => ({ key, name, file })),
      footer_text: footerText.value,
    }
    if (section === 'identity') {
      payload.title = { ...form.title }
      payload.description = { ...form.description }
      payload.logo = await resolveLogo()
      payload.favicon = await resolveFavicon()
    } else if (section === 'appearance') {
      payload.accent_mode = form.accentMode
      payload.accent_color = form.accentColor
      payload.accent_colors = [...form.accentColors]
      payload.font_headings = form.fontHeadings
      payload.font_body = form.fontBody
      payload.font_special = form.fontSpecial
      payload.custom_fonts = form.customFonts.map(({ key, name, file }) => ({ key, name, file }))
    } else if (section === 'footer') {
      payload.footer_text = { ...form.footerText }
    } else {
      payload.index_backgrounds = await resolveIndexBackgrounds()
    }
    await api.put('/admin/settings/site', payload)
    // Lo enviado pasa a ser el estado persistido que muestran las cards.
    title.value = payload.title
    description.value = payload.description
    logo.value = payload.logo
    favicon.value = payload.favicon
    indexBackgrounds.value = payload.index_backgrounds
    accentMode.value = payload.accent_mode
    accentColor.value = payload.accent_color
    accentColors.value = payload.accent_colors
    fontHeadings.value = payload.font_headings
    fontBody.value = payload.font_body
    fontSpecial.value = payload.font_special
    footerText.value = payload.footer_text
    if (section === 'appearance') customFonts.value = [...form.customFonts]
    toast.success(t('settings.toast.saved'))
    editing.value = null
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
    <!-- Dos columnas explícitas (masonry determinista): cada columna apila
         sus dos cards (Identidad + Apariencia | Pie + Fondos); en estrecho
         las columnas se apilan -->
    <div class="settings-view__columns">
      <div class="settings-view__col">
        <!-- Identidad -->
        <InfoBlock :title="t('settings.sections.identity')">
          <template #actions>
            <BaseButton variant="info" @click="openEdit('identity')">
              <template #icon><SquarePen :size="14" /></template>
              {{ t('common.actions.edit') }}
            </BaseButton>
          </template>
          <dl class="info-list">
            <dt>{{ t('settings.fields.title') }}</dt>
            <dd>{{ tr(title) || '—' }}</dd>
            <dt>{{ t('settings.fields.description') }}</dt>
            <dd>{{ tr(description) || '—' }}</dd>
            <dt>{{ t('settings.fields.logo') }}</dt>
            <dd>
              <img v-if="logoUrl" :src="logoUrl" alt="" class="settings-view__thumb" />
              <template v-else>—</template>
            </dd>
            <dt>{{ t('settings.fields.favicon') }}</dt>
            <dd>
              <img
                v-if="favicon"
                :src="favicon"
                alt=""
                class="settings-view__thumb settings-view__thumb--icon"
              />
              <template v-else>—</template>
            </dd>
          </dl>
        </InfoBlock>

        <!-- Apariencia -->
        <InfoBlock :title="t('settings.sections.appearance')">
          <template #actions>
            <BaseButton variant="info" @click="openEdit('appearance')">
              <template #icon><SquarePen :size="14" /></template>
              {{ t('common.actions.edit') }}
            </BaseButton>
          </template>
          <dl class="info-list">
            <dt>{{ t('settings.fields.accentMode') }}</dt>
            <dd>{{ t(`settings.accentModes.${accentMode}`) }}</dd>
            <dt>{{ t('settings.fields.accentColor') }}</dt>
            <dd v-if="accentMode === 'fixed'" class="settings-view__color-value">
              <span class="settings-view__swatch" :style="{ background: accentColor }" />
              <code>{{ accentColor }}</code>
            </dd>
            <dd v-else>
              <ul v-if="accentColors.length" class="settings-view__colors">
                <li v-for="color in accentColors" :key="color">
                  <span class="settings-view__swatch" :style="{ background: color }" />
                  <code>{{ color }}</code>
                </li>
              </ul>
              <template v-else>—</template>
            </dd>
            <dt>{{ t('settings.fields.fontHeadings') }}</dt>
            <dd>{{ fontLabel(fontHeadings) }}</dd>
            <dt>{{ t('settings.fields.fontBody') }}</dt>
            <dd>{{ fontLabel(fontBody) }}</dd>
            <dt>{{ t('settings.fields.fontSpecial') }}</dt>
            <dd>{{ fontLabel(fontSpecial) }}</dd>
            <dt>{{ t('settings.fields.customFonts') }}</dt>
            <dd>{{ customFontNames || '—' }}</dd>
          </dl>
        </InfoBlock>
      </div>

      <div class="settings-view__col">
        <!-- Pie de página -->
        <InfoBlock :title="t('settings.sections.footer')">
          <template #actions>
            <BaseButton variant="info" @click="openEdit('footer')">
              <template #icon><SquarePen :size="14" /></template>
              {{ t('common.actions.edit') }}
            </BaseButton>
          </template>
          <dl class="info-list">
            <dt>{{ t('settings.fields.footerText') }}</dt>
            <dd>{{ footerExcerpt || '—' }}</dd>
          </dl>
        </InfoBlock>

        <!-- Fondos de los índices -->
        <InfoBlock :title="t('settings.sections.indexBackgrounds')">
          <template #actions>
            <BaseButton variant="info" @click="openEdit('indexBackgrounds')">
              <template #icon><SquarePen :size="14" /></template>
              {{ t('common.actions.edit') }}
            </BaseButton>
          </template>
          <p class="settings-view__hint">{{ t('settings.indexBackgrounds.hint') }}</p>
          <dl class="info-list">
            <template v-for="(labelKey, key) in INDEX_BACKGROUND_KEYS" :key="key">
              <dt>{{ t(`settings.indexBackgrounds.${labelKey}`) }}</dt>
              <dd>
                <img
                  v-if="indexBackgrounds[key]"
                  :src="indexBackgrounds[key]!"
                  alt=""
                  class="settings-view__thumb"
                />
                <template v-else>—</template>
              </dd>
            </template>
          </dl>
        </InfoBlock>
      </div>
    </div>

    <!-- Modal de la sección en edición: SOLO sus campos; guardar sube las
         imágenes pendientes (diferidas) y manda el PUT completo mezclado -->
    <EditModal
      v-model="modalOpen"
      :title="modalTitle"
      :size="modalSize"
      :loading="saving"
      :submit-label="t('common.save')"
      :cancel-label="t('common.cancel')"
      @submit="save"
    >
      <!-- Identidad -->
      <template v-if="editing === 'identity'">
        <TranslatableInput
          v-model="form.title"
          :locales="locales.locales"
          :label="t('settings.fields.title')"
        />
        <TranslatableInput
          v-model="form.description"
          :locales="locales.locales"
          :label="t('settings.fields.description')"
          type="textarea"
          :rows="2"
        />
        <div class="settings-view__uploads">
          <!-- Logo por idioma (fallback al por defecto en la web): DIFERIDO,
               la subida real no viaja hasta el guardar. -->
          <TranslatableImage
            v-model="form.logo"
            :locales="locales.locales"
            :label="t('settings.fields.logo')"
          />
          <ImageUpload
            v-model="form.faviconFile"
            :current-url="form.faviconCurrent"
            :label="t('settings.fields.favicon')"
            accept=".png,.svg"
            :drag-text="t('common.imageDrag')"
            :hint-text="t('settings.fields.faviconHint')"
            @remove="onRemoveFavicon"
          />
        </div>
      </template>

      <!-- Apariencia -->
      <template v-else-if="editing === 'appearance'">
        <BaseSelect
          v-model="form.accentMode"
          :label="t('settings.fields.accentMode')"
          :options="[
            { value: 'fixed', label: t('settings.accentModes.fixed') },
            { value: 'random', label: t('settings.accentModes.random') },
          ]"
        />

        <PaletteColorPicker
          v-if="form.accentMode === 'fixed'"
          v-model="form.accentColor"
          :label="t('settings.fields.accentColor')"
        />

        <template v-else>
          <p class="settings-view__hint">{{ t('settings.fields.accentColorsHint') }}</p>
          <!-- Candidatos como etiquetas en fila (con wrap) -->
          <ul v-if="form.accentColors.length" class="settings-view__colors">
            <li v-for="(color, index) in form.accentColors" :key="color">
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
              v-model="form.fontHeadings"
              :label="t('settings.fields.fontHeadings')"
              :options="fontOptions"
            />
            <p
              class="settings-view__font-preview"
              :style="{ fontFamily: fonts[form.fontHeadings]?.stack }"
            >
              {{ t('settings.fontPreviewHeading') }}
            </p>
          </div>
          <div>
            <BaseSelect
              v-model="form.fontBody"
              :label="t('settings.fields.fontBody')"
              :options="fontOptions"
            />
            <p
              class="settings-view__font-preview"
              :style="{ fontFamily: fonts[form.fontBody]?.stack }"
            >
              {{ t('settings.fontPreviewBody') }}
            </p>
          </div>
          <!-- Fuente "especial": acentos puntuales (hoy, el bloque cita) -->
          <div>
            <BaseSelect
              v-model="form.fontSpecial"
              :label="t('settings.fields.fontSpecial')"
              :options="fontOptions"
            />
            <p
              class="settings-view__font-preview"
              :style="{ fontFamily: fonts[form.fontSpecial]?.stack }"
            >
              {{ t('settings.fontPreviewSpecial') }}
            </p>
          </div>
        </div>

        <!-- Fuentes propias: subir un fichero la hace elegible arriba -->
        <div class="settings-view__custom-fonts">
          <span class="form-field__label">{{ t('settings.fields.customFonts') }}</span>
          <ul v-if="form.customFonts.length" class="settings-view__colors">
            <li v-for="font in form.customFonts" :key="font.key">
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
      </template>

      <!-- Pie de página -->
      <template v-else-if="editing === 'footer'">
        <TranslatableInput
          v-model="form.footerText"
          :locales="locales.locales"
          :label="t('settings.fields.footerText')"
          type="wysiwyg"
          :rich-labels="richLabels"
        />
      </template>

      <!-- Fondos de los índices: una imagen (opcional) por página índice de
           la web pública. DIFERIDO como el favicon: nada se sube ni se
           borra hasta el guardar. -->
      <template v-else-if="editing === 'indexBackgrounds'">
        <p class="settings-view__hint">{{ t('settings.indexBackgrounds.hint') }}</p>
        <div class="settings-view__index-bgs">
          <ImageUpload
            v-for="(labelKey, key) in INDEX_BACKGROUND_KEYS"
            :key="key"
            :model-value="indexBackgroundFile(key)"
            :current-url="indexBackgroundUrl(key)"
            :label="t(`settings.indexBackgrounds.${labelKey}`)"
            :drag-text="t('common.imageDrag')"
            @update:model-value="onIndexBackgroundFile(key, $event)"
            @remove="onRemoveIndexBackground(key)"
          />
        </div>
      </template>
    </EditModal>
  </div>
</template>
