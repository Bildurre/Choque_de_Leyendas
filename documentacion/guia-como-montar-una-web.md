# Guía: cómo montar una web nueva (juego) sobre el motor

Guía práctica y **paso a paso** para crear un juego nuevo con EdC Motor
(EdC) y para añadirle entidades (modelos), iconos y vistas. Ejemplos de
referencia en el `playground` (cópialos y adáptalos): **House** (CRUD básico),
**Scheme** (argucia — relación `belongsTo` + single + modo carta) y **Character**
(personaje — campos calculados + single + modo carta).

> Mantenimiento: **esta guía se actualiza cada vez que cambia el flujo de alta de
> un juego, los traits del motor o el scaffolding de vistas.** Va de la mano de
> [`guia-de-componentes.md`](guia-de-componentes.md) (los componentes) y del
> [registro de decisiones](03-decisiones-cerradas.md) (los porqués, DC-xx).

---

## 0. Modelo mental

- El **motor** (`edc-motor/core`, `@edc-motor/ui`, `@edc-motor/admin-kit`) aporta lo común: auth,
  media, i18n, traits, componentes, layout, biblioteca de iconos. **No se toca
  por juego.**
- Cada **juego** es su propio repo/monorepo con tres piezas que **consumen** el
  motor y programan **solo sus entidades**:
  - `api/` — Laravel + Sanctum (usa `edc-motor/core` vía Composer _path repository_).
  - `admin/` — SPA Vue (panel) que usa `@edc-motor/admin-kit` + `@edc-motor/ui`.
  - `app/` — SPA Vue (público) que usa `@edc-motor/ui`.
  - `packages/shared/` — lo del juego compartido entre admin y app: los
    **componentes visuales de las entidades** (la carta que se ve en la web y
    se captura a PNG, D8), sus tipos y su SCSS.
- Regla de oro (DC-28): si necesitas un componente/patrón, **míralo primero en
  kontuan** (y para cartas, en CDL); si existe, cópialo y adáptalo.

---

## 1. Crear el juego (una vez)

1. Estructura: un monorepo con `api/`, `admin/`, `app/` y `packages/shared/` (mira el `playground/`).
2. En `api/composer.json`, añade el motor como _path repository_:

   ```json
   "repositories": [{ "type": "path", "url": "../../packages/core" }],
   "require": { "edc-motor/core": "*" }
   ```

3. `api/bootstrap/app.php`: registra el `SetLocale` del motor en el grupo `api`
   (imprescindible para que las respuestas se traduzcan por `?locale`):

   ```php
   ->withMiddleware(function (Middleware $middleware): void {
       $middleware->appendToGroup('api', \Edc\Core\I18n\Http\Middleware\SetLocale::class);
   })
   ```

4. Front: cada SPA lee la API de `VITE_API_URL` (`.env`), sin proxy. El admin
   usa `createApi()` de `@edc-motor/ui` (token Sanctum + 401).
5. Instala y prepara:

   ```bash
   npm install
   cd api && php artisan migrate            # crea tablas del motor (icons, …) + las del juego
   php artisan storage:link                 # para servir imágenes en /storage
   php artisan db:seed                      # usuarios base (admin/editor/user)
   ```

6. Datos de prueba: **avisa antes de insertar datos** de entidades; el seeder
   base solo crea usuarios.
7. **Cola**: en desarrollo deja `QUEUE_CONNECTION=sync` (PNG y PDF se
   generan en la propia petición, sin worker). En producción usa `database`
   + `php artisan queue:work` — con `database` y SIN worker los PDF se
   quedan en `pending` para siempre y "no aparecen" en el admin ni en las
   Descargas.

---

## 2. Backend — añadir un modelo

Sigue el patrón de `House`. Cuatro archivos: migración, modelo, controlador,
resource; y las rutas.

### 2.1 Migración (DC-24)

- Campos **traducibles** → columnas `json`. Fechas con `datetimes()` y, si hay
  papelera, `softDeletesDatetime()` (NO `timestamps()`; evita el año 2038).
- Nombres de tabla/columna en **inglés**.

```php
Schema::create('houses', function (Blueprint $table) {
    $table->id();
    $table->json('name');                 // traducible
    $table->json('description')->nullable();
    $table->json('slug');                 // slug traducible (uno por locale)
    $table->string('color', 7)->nullable();
    $table->boolean('is_published')->default(false);
    $table->datetimes();
    $table->softDeletesDatetime();        // si quieres papelera
});
```

### 2.2 Modelo + traits disponibles

```php
class House extends Model implements HasMedia
{
    use HasFilters, HasImage, HasPublishedState, HasTranslatableSlug,
        HasTranslations, ResolvesBySlug, SoftDeletes;

    protected $table = 'houses';
    protected $fillable = ['name', 'description', 'slug', 'color', 'is_published'];
    public array $translatable = ['name', 'description', 'slug'];   // Spatie Translatable
    protected array $searchable = ['name'];                         // columnas para el buscador
    protected function casts(): array { return ['is_published' => 'boolean']; }

    public function getSlugOptions(): SlugOptions {                 // Spatie Sluggable
        return SlugOptions::createWithLocales(array_keys(config('motor.locales', ['es' => []])))
            ->generateSlugsFrom('name')->saveSlugsTo('slug');
    }
}
```

**Traits del motor** (`Edc\Core\…`) y qué aporta cada uno:

| Trait | Para qué | Requiere / expone |
|---|---|---|
| `Support\Concerns\HasFilters` | Buscador + filtro de estado del listado. | Propiedad `$searchable` (columnas). Scope `filter(['search','status'])` con `status = published\|draft\|trashed`. |
| `Support\Concerns\HasPublishedState` | Publicar / borrador. | Columna `is_published`. Scopes `published()` / `draft()` y método `togglePublished()`. |
| `Support\Concerns\ResolvesBySlug` | Resolver por slug en **cualquier** idioma (rutas por slug). | Columna `slug` (json). Scope `whereSlug($slug)`. |
| `Media\Concerns\HasImage` | Imagen única (emblema, portada…). | `implements HasMedia`. Métodos `imageUrl()` (URL sobre el host de la petición) y `setImageFromRequest($request)`. |

**Traits de Spatie** que se combinan:

| Trait | Para qué |
|---|---|
| `Spatie\Translatable\HasTranslations` | Campos traducibles (`$translatable`, columnas json). |
| `Spatie\Sluggable\HasTranslatableSlug` | Slug **por idioma** (con `getSlugOptions()`). |
| `Illuminate\Database\Eloquent\SoftDeletes` | Papelera (restaurar / borrar definitivo). |

#### Relaciones (ejemplo: Scheme pertenece a House)

Eloquent normal. Columna FK en la migración
(`$table->foreignId('house_id')->constrained('houses')->cascadeOnDelete();`) y las
relaciones en ambos modelos:

```php
// Scheme.php
public function house(): BelongsTo { return $this->belongsTo(House::class); }
// House.php
public function schemes(): HasMany { return $this->hasMany(Scheme::class); }
```

- En el listado, **eager-load** la relación para la tarjeta: `Scheme::with('house')->filter(...)`.
- Para el **selector** del formulario (elegir la casa), expón una lista ligera:
  un endpoint `GET /admin/houses/options` (id + nombre) y un store en el front.
- El **single** del "padre" puede listar sus hijos: `House::with('schemes')` en
  `show()` + `SchemeResource::collection($this->whenLoaded('schemes'))` en el resource.

#### Campos calculados (ejemplo: Character.coste/defensa)

Cuando un campo se deriva de otros, **no lo recibas del cliente**: recalcúlalo en
el modelo. Coste = suma de estadísticas (se persiste); defensa = coste (accessor,
sin columna):

```php
protected static function booted(): void {
    static::saving(fn (Character $c) =>
        $c->cost = (int) $c->power + $c->prestige + $c->intrigue + $c->money);
}
public function getDefenseAttribute(): int { return (int) $this->cost; }
```

El controlador valida solo las estadísticas (no `cost`); el resource expone
`defense => $this->defense`. En el front, muestra el valor calculado de solo
lectura (recalculado en vivo con un `computed`).

### 2.3 Controlador (admin)

- `index`: `Model::query()->filter($request->only('search','status'))->orderByDesc('id')->paginate(15)`.
- `show/update/destroy/toggle`: resuelven por slug con `whereSlug($slug)->firstOrFail()`.
- `restore` y `forceDestroy`: por **id** con `withTrashed()` (actúan desde la papelera).
- Guarda traducciones con `setTranslations('name', array_filter($data['name'] ?? []))`.
- Imagen: `$model->setImageFromRequest($request)` (tras `save()`).

### 2.4 Resource

Devuelve las traducciones completas para editar y la URL de imagen:

```php
'name' => $this->getTranslations('name'),
'slug' => $this->getTranslations('slug'),
'image' => $this->imageUrl(),
```

### 2.5 Rutas (`api/routes/api.php`)

Público solo publicadas; admin bajo `auth:sanctum` + `motor.admin`. Detalle por
`{slug}`, papelera por `{id}`:

```php
Route::middleware(['auth:sanctum','motor.admin'])->prefix('admin')->group(function () {
    Route::get('houses', [HouseController::class, 'index']);
    Route::post('houses', [HouseController::class, 'store']);
    Route::get('houses/{slug}', [HouseController::class, 'show']);
    Route::put('houses/{slug}', [HouseController::class, 'update']);
    Route::delete('houses/{slug}', [HouseController::class, 'destroy']);
    Route::post('houses/{id}/restore', [HouseController::class, 'restore']);
    Route::delete('houses/{id}/force', [HouseController::class, 'forceDestroy']);
    Route::post('houses/{slug}/toggle-published', [HouseController::class, 'togglePublished']);
});
```

> Las rutas propias del juego van en `api/routes/api.php` (grupo `api`
> automático). Las del **motor** ya vienen dadas por `edc-motor/core` (auth, locales,
> iconos…) y usan `Route::prefix('api')->middleware('api')`.

### 2.6 Validación traducida

Provee `api/lang/{es,eu,en}/validation.php` (mensajes + `attributes` con los
nombres de campo). El locale lo fija `SetLocale` desde `?locale`.

---

## 3. Iconos (biblioteca del juego)

La biblioteca de iconos la aporta el **motor** (DC-32): no hay que programar
nada en el backend del juego.

- **Gestión:** en el admin, sección **Iconos** → subir SVG/PNG (nombre +
  imagen). Endpoints del motor: `GET /api/icons`, `POST`/`DELETE /api/admin/icons`.
- **Uso en el texto:** cualquier campo `TranslatableInput type="wysiwyg"` que
  reciba `:icons` muestra un selector que inserta el icono **en línea**
  (`<img class="rt-icon">`). En el front, cárgalos con el store de iconos y
  pásalos al editor:

  ```ts
  const icons = useIconsStore(); await icons.load()
  // <TranslatableInput type="wysiwyg" :icons="icons.icons.filter(i=>i.url)…" />
  ```

---

## 4. Frontend admin — añadir las vistas del modelo

Todo esto vive en el **juego** (`admin/src`), componiendo piezas de
`@edc-motor/admin-kit` + `@edc-motor/ui`. Nada de tablas: **grid de tarjetas** (DC-30), y
altas/ediciones en **modal** (DC-31).

### 4.1 i18n (`admin/src/i18n/locales/*.json`)

Añade por cada idioma: el segmento de ruta, las migas, el nav y la sección del
modelo (tabs, columnas, campos, acciones, toasts, confirmaciones):

```jsonc
"routes":      { "houses": "casas" },      // segmento traducido de la URL
"breadcrumbs": { "houses": "Casas" },
"nav":         { "houses": "Casas" },
"houses":      { "title": "...", "tabs": {…}, "fields": {…}, "actions": {…}, "toast": {…} }
```

### 4.2 Ruta localizada (`admin/src/router/i18n-paths.ts`)

Dos rutas por entidad: **listado** y **detalle** (`:slug`). El `path` usa el
segmento del locale actual y `alias` los de los demás (para que resuelva en
cualquier idioma). Las **altas/ediciones no son rutas** (son modales).

```ts
{ path: `/${p.houses}`, name: 'houses',
  component: () => import('@/views/houses/HousesListView.vue'),
  alias: buildAliases((t) => `/${t.houses}`, locale),
  meta: { admin: true, titleKey: 'houses.title', breadcrumbs: [{ key: 'houses' }] } },
{ path: `/${p.houses}/:slug`, name: 'house-single',
  component: () => import('@/views/houses/HouseSingleView.vue'),
  alias: buildAliases((t) => `/${t.houses}/:slug`, locale),
  meta: { admin: true, titleKey: 'houses.title', breadcrumbs: [{ key: 'houses', to: 'houses' }] } }
```

### 4.3 Enlace en el menú (`admin/src/App.vue`)

```vue
<RouterLink class="nav-item" :to="{ name: 'houses' }">
  <Home class="nav-icon" :size="20" /><span class="nav-label">{{ t('nav.houses') }}</span>
</RouterLink>
```

### 4.4 Vista de listado (`admin/src/views/<modelo>/XListView.vue`)

Orden fijo: **filtros → tabs → grid de tarjetas**. La lógica común de todos los
listados (tabs + búsqueda con debounce, modal de alta/edición, acciones de fila
con confirmación + toast + manejo de errores, `tr()`/`slugFor()`) vive en el
composable **`useEntityList`** (`admin/src/composables/useEntityList.ts`): la
vista solo declara su template.

```ts
const { t, items, loading, status, search, tabs, tr, init, formOpen, formMode,
  formSlug, openCreate, edit, goSingle, onSaved, togglePublish, del, restore,
  forceDelete, selectedId, selected, select, hasPreview } = useEntityList<House>({
  resource: '/admin/houses',
  ns: 'houses',                 // namespace i18n (tabs/confirm/toast del modelo)
  singleRoute: 'house-single',
  nameOf: (item) => item.name,  // para los mensajes de confirmación
})
onMounted(init)
```

```vue
<FilterBar v-model="search" :placeholder="t('common.search')" />
<BaseTabs v-model="status" :tabs="tabs" />                <!-- published/draft/trashed -->
<EmptyState v-if="!loading && !items.length" :title="t('common.empty')" />
<BaseGrid v-else preset="cards" gap="md">
  <EntityCard v-for="item in items" :key="item.id" :title="tr(item.name)"
    :muted="!!item.deleted_at" :active="selectedId === item.id" clickable @view="select(item)">
    <template #media>…emblema…</template>
    <template #actions>…IconButton editar + abrir (las básicas; el resto en el panel)…</template>
    <template #badges>…chip de estado…</template>
    <template #meta>…datos…</template>
  </EntityCard>
</BaseGrid>
<XFormModal v-model="formOpen" :mode="formMode" :target-slug="formSlug" @saved="onSaved" />
<EntityPanel :item="selected" :name="selected ? tr(selected.name) : ''"
  :kicker="t('houses.panelTitle')" :empty="t('houses.panelEmpty')" :has-preview="hasPreview"
  @open="selected && goSingle(selected)" … />
```

- `tr(obj)` = valor en el locale activo (con fallback al locale por defecto).
- **Selección → panel derecho** (patrón kontuan): la tarjeta entera selecciona
  (`select(item)` viene de `useEntityList`, que registra el sidebar con
  `` t(`${ns}.panelTitle`) `` y hace `reveal()`); `EntityPanel`
  (`admin/src/components/EntityPanel.vue`) pinta TODAS las acciones arriba del
  todo (abrir, editar, publicar/despublicar, regenerar PNG si `previewKey`,
  borrar — o restaurar/definitivo en papelera), la info del elemento (slot
  `meta`) y las imágenes por idioma si la entidad es renderizable. Claves
  i18n del modelo: `panelTitle` y `panelEmpty`.
- Editar → abre el modal con el **slug del locale activo**.
- Acciones por slug: borrar y `toggle-published`; restaurar/borrado
  definitivo por id. Todas confirman (las destructivas), avisan con toast y
  capturan errores (`common.errors.action` / `common.errors.load`).
- Textos comunes en `common.actions.*` (abrir/editar/publicar/borrar/restaurar…);
  los específicos del modelo (tabs, toasts, confirmaciones) en su namespace.

### 4.5 Modal de alta/edición (`admin/src/components/<modelo>/XFormModal.vue`)

Envuelve `EditModal`. Recibe `modelValue` + `mode: 'create'|'edit'` + `targetSlug`;
al abrir en edición carga por slug; emite `saved`.

```vue
<EditModal :model-value="modelValue" :title="title" :loading="saving"
  :submit-label="t('common.save')" :cancel-label="t('common.cancel')"
  @update:model-value="v => emit('update:modelValue', v)" @submit="submit">
  <TranslatableInput v-model="form.name" :locales="locales.locales" :label="t('…name')" required :error="errors.name" />
  <TranslatableInput v-model="form.description" :locales="locales.locales" type="wysiwyg" :icons="iconList" :label="t('…desc')" />
  <ImageUpload v-model="image" :current-url="currentImage" :label="t('…image')"
    :too-large-text="t('common.fileTooLarge')" :invalid-type-text="t('common.fileType')" />
  <PaletteColorPicker v-model="form.color" :label="t('…color')" />
  <BaseCheckbox v-model="form.is_published" :label="t('…published')" />
</EditModal>
```

Elementos de formulario disponibles (todos de `@edc-motor/ui`, ver la guía de
componentes): `BaseInput`, `BaseTextarea`, `BaseSelect`, `BaseCheckbox`,
`TranslatableInput` (text/textarea/**wysiwyg**), `RichTextInput`, `ImageUpload`
(drag&drop), `PaletteColorPicker` (hex).

Envío con `FormData` (para la imagen); en edición, `updateForm` añade
`_method=PUT`.

### 4.6 Detalle (single) + "modo carta"

Para entidades con vista de detalle (ejemplos: `Scheme`, `Character`, `House`):

- **`XSingleView.vue`** (`admin/src/views/<modelo>/`): carga por slug con
  `useResource().find(route.params.slug)`; barra con **volver** + **Editar** (abre
  el `XFormModal` en modo `edit` y recarga en `@saved`); muestra el preview y la
  info. Las tarjetas del listado enlazan aquí (`EntityCard clickable @view`).
- **`XCard.vue`** (`admin/src/components/<modelo>/`): la **composición "modo
  carta"** por atributos, de tamaño fijo (`.play-card`), **lista para renderizar a
  PNG** (Fase 3). Recibe `item` + `locale` y resuelve las traducciones dentro.
- Contenido enriquecido (descripción WYSIWYG): renderízalo con `v-html` dentro de
  un `.rich-content` (aplica el estilo de los iconos en línea `.rt-icon`).
- Un single "padre" puede listar sus hijos (p. ej. House lista sus argucias) con
  un `BaseGrid` de `EntityCard` que enlazan al single del hijo.

### 4.7 Validación en cliente + errores

- Comprueba en cliente lo requerido **antes** de enviar (buena UX y evita 422
  innecesarios); marca los campos con `:error`.
- En el `catch`: **nunca** muestres el mensaje crudo del servidor. Usa
  `fieldErrors(e)` (`admin/src/lib/apiError.ts`) para pintar errores por campo
  (422, ya traducidos) + un **toast genérico**.

### 4.8 Estilos (DC-27)

Nada de `<style>` en los `.vue`. Crea `admin/src/assets/scss/views/_<modelo>.scss`
con clases BEM y decláralo en `views/_index.scss`.

---

## 5. Render a PNG de una entidad (previews, Fase 3)

Para que una entidad se capture a PNG (base del PDF), doc `funcionalidades/01-render-png.md`:

1. **Migración**: columna `->json('preview_image')->nullable()`.
2. **Modelo**: `implements PreviewableContract` + `use HasPreviewImage`, y declara:
   - `previewSize($type)` — px CSS del componente (proporcional al layout de
     impresión: regla 5 px/mm; la carta Magic: 315×440),
   - `previewTriggerFields()` — campos cuyo cambio regenera (is_published no),
   - `renderData($locale, $type)` — payload que consumirá el componente en `/_render`.
3. **Registro backend** (`AppServiceProvider::boot`):
   `Previews::register('character', Character::class);`
4. **Componente visual** en `packages/shared` del juego (fuente única web +
   captura, D8) y **registro frontend** en `app/src/render/registry.ts`:
   `{ character: CharacterCard }`. La ruta `/_render/:entity/:id` ya la trae el
   andamiaje de la app (desnuda, con token DC-04 y señal `__bgmRenderReady`).
5. **Invalidación por imagen**: la imagen vive en MediaLibrary (no es columna);
   tras `setImageFromRequest()` llama a `regeneratePreviews()` en el controlador.
6. **Resource**: expón `'previews' => $this->previewUrls()`; en el single del
   admin añade `<PreviewPanel entity="character" :id="item.id" />`.
7. **Gestor en el admin**: monta la vista "Imágenes" con el `PreviewManager`
   del admin-kit (`<PreviewManager :api="api" :labels="labels" />`, ver
   `playground/admin/src/views/previews/PreviewsView.vue`): estado por tipo,
   lotes, acciones por entidad y limpieza de huérfanos.
8. En marcha: la creación/edición encola los renders (worker de cola;
   `npm run dev` ya lo levanta). A mano: `php artisan preview:manage
   generate|regenerate|status|delete|clean [--type --id --locale --sync --dry-run]`.

### 5.1 Varias previews por modelo

**Una preview = una clave del registro.** Un modelo puede registrarse bajo
varias claves para tener una preview por defecto y otras especiales (p. ej.
para PDF distintos); la por defecto es la primera registrada:

```php
Previews::register('house', House::class);         // token 40 mm (por defecto)
Previews::register('house-counter', House::class); // contador 25 mm
```

La clave llega a `previewSize($type)` y `renderData($locale, $type)` para
variar tamaño o datos, y al `renderRegistry` de la app para elegir componente
(puede ser el mismo a otra escala: dimensiona con unidades de contenedor,
`cqw`). Cada clave tiene su carpeta (`previews/house-counter/...`), su sección
en el gestor de imágenes del admin y su vida propia; la columna
`preview_image` guarda `clave => (locale => ruta)`. Al crear/editar se
regeneran TODAS las previews del modelo. En los PDF, el export elige cuál
imprime (§6.1).

Requisitos: `npm install` en `api/` (instala `puppeteer`) **y su navegador**
(`npm run chrome:install` si npm saltó el postinstall — error "Could not find
Chrome"), o `MOTOR_CHROME_PATH` apuntando a un Chromium del sistema. Apagado
global con `MOTOR_PREVIEWS=false`.

## 6. PDF de un juego (exports, plantillas y layouts)

El motor pone el **pipeline entero** (cola, ensamblado con DomPDF, almacenamiento
versionado, regeneración con un clic, colección temporal del usuario, limpieza);
el juego solo **describe el contenido** de cada PDF con un *export*. Frontera:
el motor nunca sabe qué es una "casa" o un "mazo"; el juego nunca reescribe el
ensamblado.

### 6.1 Tipos de export (el catálogo del juego)

Un export es una clase pequeña en `api/app/Pdf/` que extiende `Edc\Core\Pdf\PdfExport`
y declara: quién es la entidad dueña (`sourceModel()`) y qué ítems van dentro
(`items()`). El conjunto de exports registrados ES el catálogo de PDF del juego:
define **qué PDF se pueden generar y qué contiene cada uno**, y la sección PDF
del admin lo pinta solo. Las formas habituales (el playground trae las dos
primeras):

1. **Colección por entidad** — el PDF pertenece a una entidad y agrega sus
   hijas. Ej.: las argucias de una casa (`HouseSchemesExport`):

   ```php
   class HouseSchemesExport extends PdfExport
   {
       public function sourceModel(): ?string { return House::class; }

       public function items(?Model $source, string $locale): array
       {
           return $source->schemes()->published()->get()
               ->map(fn (Scheme $s) => PrintableItem::preview($s))
               ->all();
       }
   }
   ```

   Para que el gestor del admin liste las casas, el export por entidad
   implementa también `sources($locale)` (id + etiqueta legible):

   ```php
   public function sources(string $locale): array
   {
       return House::query()->orderBy('id')->get()
           ->map(fn (House $h) => ['id' => $h->id, 'label' => $h->getTranslation('name', $locale)])
           ->all();
   }
   ```

2. **Colección global** — sin dueña (`sourceModel()` devuelve `null`). Ej.:
   todas las cartas de personaje (`CharactersExport`) o todas las argucias
   (`SchemesExport`).

3. **Otro tipo de piezas (tokens, contadores, losetas…)** — un export es
   contenido + layout, así que no está limitado a cartas. Ej. del playground:
   un PDF con **9 tokens redondos de 2 cm de radio por cada casa**
   (`HouseTokensExport`): la entidad se hace renderizable con su componente
   (aquí `HouseToken`, un círculo de 40×40 mm) y el export repite cada una:

   ```php
   class HouseTokensExport extends PdfExport
   {
       public function sourceModel(): ?string { return null; } // global

       public function items(?Model $source, string $locale): array
       {
           return House::query()->published()->get()
               ->map(fn (House $h) => PrintableItem::preview($h, copies: 9))
               ->all();
       }

       public function layout(): string { return 'token-40'; } // preset propio (§6.3)
   }
   ```

4. **Individual / con copias** — nada lo impide: un export con
   `PrintableItem::preview($source, copies: N)` imprime una entidad repetida.
   Este playground no usa cartas individuales; cada juego decide su catálogo.

Los ítems se declaran con `PrintableItem::preview($entidad, copies: N)` (usa el
PNG del render, doc 01, **generándolo al vuelo si falta**) o
`PrintableItem::image($rutaOUrl, copies: N)` para imágenes arbitrarias. Si el
modelo tiene varias previews (§5.1), el export **elige cuál imprime** con
`preview:`; en el playground, `house-counters` imprime la segunda preview de
la casa en el layout `counter` del motor:

```php
PrintableItem::preview($casa, copies: 9, preview: 'house-counter');
```

Registro en `AppServiceProvider::boot` (la clave es el `type` de la API):

```php
Pdfs::register('characters', CharactersExport::class);      // todos los personajes (card-big)
Pdfs::register('schemes', SchemesExport::class);            // todas las argucias (card)
Pdfs::register('house-schemes', HouseSchemesExport::class); // un PDF por casa
Pdfs::register('house-tokens', HouseTokensExport::class);   // 9 tokens por casa (token-40)
```

### 6.2 Plantillas (vistas del PDF)

- **La generalista del motor** (`motor::pdf.grid`): rejilla de imágenes a tamaño
  físico exacto con **marcas de corte**, paginada según el layout. Es la que usan
  todos los exports por defecto y vale para cartas, counters y cualquier pieza
  recortable. No hay que tocarla nunca.
- **Una vista propia por export**: si un juego quiere un PDF especial (portada,
  reglas maquetadas, dorso+frente…), su export sobreescribe `view()` apuntando a
  una Blade del juego (`api/resources/views/pdf/...`). Recibe `$pdf`, `$pages`
  (huecos ya expandidos y paginados) y `$layout`. El resto del pipeline
  (cola, versionado, regeneración, gestor del admin) sigue siendo del motor.

### 6.3 Layouts de impresión: el tamaño de cada pieza (DC-07)

Un layout es un preset con papel, orientación, **tamaño de pieza en mm**,
margen, separación y marcas de corte; el motor calcula columnas/filas/capacidad
del papel. Por defecto `card` es **tamaño Magic (63×88 mm, 9 por A4)** y
`counter` 25×25. El juego declara los suyos en el `boot` de su
`AppServiceProvider`, junto a los exports (basta con las claves que cambian):

```php
Pdfs::layout('card-big', [ // doble Magic: 2 por A4 apaisado
    'orientation' => 'landscape',
    'item_width' => 126, 'item_height' => 176,
]);
Pdfs::layout('token-40', ['item_width' => 40, 'item_height' => 40]);
```

(Alternativa equivalente: publicar la config con `php artisan vendor:publish
--tag=motor-config` y editar `motor.pdf.layouts`; útil si ya la publicas por
otros motivos.)

**El tamaño se elige por export, no por entidad**: cada export devuelve su
preset en `layout()`. Así, en el playground las argucias mantienen la carta
Magic (`SchemesExport` usa `card`) mientras los personajes se imprimen al doble
(`CharactersExport::layout()` devuelve `card-big`) y los tokens de casa usan
`token-40`. La API admite `layout` para forzar otro puntual.

> Mantén la **proporción** de `previewSize()` (px, doc 01) acorde al layout
> (mm) para que la imagen no se deforme. Regla práctica del playground:
> **5 px CSS por mm** (Magic 63×88 → 315×440 px; token 40×40 → 200×200 px),
> que con `previews.scale = 2` da ~250 ppp de impresión. Si imprimes una pieza
> mucho más grande (p. ej. `card-big`), sube `previewSize()` o
> `motor.previews.scale` para no perder nitidez.

### 6.4 Gestión desde el admin y API

- **Toda la gestión vive en la sección PDF del admin** (nada en los detalles de
  las entidades). `PdfManager` (admin-kit) lee el catálogo del backend
  (`GET /api/admin/pdfs/exports`) y pinta cada export: los globales con sus
  filas por idioma, y los por-entidad con sus entidades desplegables (de
  `sources()`), cada una con su Generar:

  ```vue
  <PdfManager :api="api" :labels="pdfLabels" :type-labels="typeLabels" />
  ```

  Las tarjetas resumen como las de Imágenes — **total de piezas y listas por
  idioma** (`stats` del catálogo) — y las acciones "de todas" son las mismas
  de las previews: **Generar faltantes** (tarjeta y panel; solo los combos
  entidad×idioma sin PDF o fallidos), **Regenerar todo** y **Borrar todo**
  (panel, con confirmación). En el panel, filas por idioma con estado (en
  cola / listo / error con mensaje) y botones Generar / Regenerar /
  Descargar / Borrar. **Generar siempre genera todos los idiomas** (el
  `?locale` que el admin añade a cada petición es el locale de contenido, no
  un limitador; para limitar, `locale` en el cuerpo). Los errores inesperados
  se muestran con un mensaje genérico — el detalle queda en los logs del
  servidor. **Regenerar = un clic**: reutiliza el registro, versiona el
  fichero y borra el anterior. Añadir un export nuevo al juego = registrarlo
  + su etiqueta en `typeLabels`; la vista no se toca.
- API: `GET /api/admin/pdfs/exports` (catálogo con `stats`),
  `GET/POST /api/admin/pdfs[...]` (gestión),
  `POST /api/admin/pdfs/generate-missing` / `regenerate-all` y
  `DELETE /api/admin/pdfs?type=x` (acciones "de todas"),
  `GET /api/pdfs/{id}/download` (permanentes públicos; temporales
  solo dueño/admin).
- **Nombre del archivo**: siempre por el **nombre de la entidad**, nunca por
  su id — `slug(nombre)-locale.pdf` (usa `previewLabel()` si el modelo lo
  tiene; si no, `name`/`title` traducidos). La unicidad la garantiza el
  sufijo aleatorio de la ruta de almacenamiento. Los de colección van como
  `collection-slug(nombre del dueño|invitado)-locale.pdf`.

### 6.5 Colección temporal del usuario

`/api/pdf-collection` (autenticado): añadir entidades renderizables con copias,
listar (con su preview y etiqueta), vaciar y `generate` → PDF **temporal** con
`expires_at` (TTL en `motor.pdf.temporary_ttl`). `php artisan pdf:cleanup` borra
los caducados — prográmalo en `api/routes/console.php`:

```php
Schedule::command('pdf:cleanup')->daily();
```

La UI pública de esta colección llega con el andamiaje de la web (Fase 6).

## 6bis. CRM de páginas y bloques (Fase 5)

### 6bis.0 Qué bloques trae el motor (catálogo)

| Tipo | Clave | Campos propios |
|---|---|---|
| Cabecera | `header` | `title`* (texto, trad.), `subtitle` (texto, trad.), `image` (banner a lo ancho) |
| Texto | `text` | `title` (texto, trad.), `body`* (richtext, trad.), `image` (imagen), `image_position` |
| Tarjeta de texto | `text-card` | `label` (texto, trad.), `title` (texto, trad.), `body`* (richtext, trad.), `image`, `image_position` |
| Cita | `quote` | `quote`* (richtext, trad.), `author` (texto, trad.), `image` (retrato del autor) |
| Índice automático | `index` | `title` (texto, trad.), `numbered` (boolean) — enlaza a los bloques posteriores indexables |
| Llamada a la acción | `cta` | `title`, `body` (richtext), `button_text`*, `button_url`* (trad.), `button_variant` (select: primary/secondary), `image`, `image_position` |

El botón del CTA usa `.block-button` (`@edc-motor/ui`), con dos variantes de
**hover cruzado**: `primary` ("Normal") = fondo de acento y texto del color
del texto, que en hover pasa a fondo del color de fondo con texto de acento;
`secondary` ("Inverso") = exactamente al revés. Ambas llevan borde de acento.

(*) obligatorio en el locale por defecto. Todas las `image` son
**multilingües** (`->translatable()`: una URL por locale, con fallback al
default). TODOS los bloques llevan además los **campos comunes** que añade el
motor, aplicados por el envoltorio `BlockShell`: `align` (select:
left/center/right/justify), **`width`** (anchura del contenido — `wide`
960px por defecto / `full` ancho completo / `narrow` 680px; da coherencia
entre bloques y entre páginas) y `background` (color, aplicado como tinte
semitransparente).

`image_position` es el campo estándar `BlockType::imagePositionField()`
(reutilizable por los bloques del juego): `top` / `left` / `right` / `bottom`
(columnas o encima/debajo) y `clear-left` / `clear-right` — la imagen queda
**flotada y el texto la rodea** siguiendo por debajo (el "cleartext" de CDL);
en pantallas estrechas la imagen pasa arriba a lo ancho.

### 6bis.0b El DSL de campos (DC-08), referencia

`Field::text|textarea|richtext|number|boolean|select|color|image('clave')`
con modificadores encadenables: `->translatable()` (el valor se guarda por
locale), `->required()` (exige el locale por defecto), `->default(v)`,
`->label('Etiqueta')` (castellano; el admin la localiza por convención),
`->min(n)` / `->max(n)` (number). `select` recibe `[valor => etiqueta]`.
De cada campo salen: el input del formulario (SchemaFields), la validación
en servidor y la localización del valor en el render. `richtext` se sanea
en servidor (HtmlSanitizer, DC-09). `image` sube al momento
(`POST /admin/content/uploads`) y guarda la URL; con `->translatable()` es
**multilingüe** (una URL por locale, editor `TranslatableImage` y fallback
al locale por defecto en el render) — así van todas las de los bloques del
motor.

**Anidados**: `Field::group('clave')->fields([...])` (objeto de subcampos),
`Field::repeater('clave')->fields([...])->min(n)->max(n)` (lista de filas
con los mismos subcampos; el editor añade/quita/reordena) y
`Field::entity('clave', '/admin/xxx/options')` (referencia por id a una
entidad del juego: buscador en el admin sobre el endpoint de opciones —
shape `{data: [{id, name: {locale}}]}` — y el `resolveData` del bloque
carga el modelo al renderizar). La validación, el saneado del richtext y la
localización funcionan RECURSIVAMENTE dentro de group/repeater. Demos: el
bloque `faq` del motor (repeater) y `featured-house` del playground
(entity).

El motor pone TODO el CRM (páginas jerárquicas traducibles con SEO y home
única, bloques reordenables, editor generado, render público con caché); el
juego solo **declara sus tipos de bloque**. Añadir un bloque son dos piezas:

1. **La clase** en `api/app/Blocks/` (extiende `Edc\Core\Content\BlockType`):
   `$key`, `fields()` con el DSL (DC-08: `Field::text|richtext|number|boolean|
   select|color|image`, con `->translatable() ->required() ->default()`) y,
   si consulta modelos del juego, `resolveData($block, $locale)`. Registro:
   `Blocks::register(MiBloque::class)` en el AppServiceProvider. Con eso ya
   está en la paleta del admin, con formulario y validación generados.
2. **El componente Vue** en `app/src/blocks/` + su entrada en
   `app/src/blocks/registry.ts` (clave = `$key`). Recibe `settings`
   (localizados) y `data` (lo de `resolveData`). Los cinco de presentación
   del motor vienen hechos (`motorBlockComponents` de `@edc-motor/ui`).

En el admin: vista Páginas (lista + modal) y single con `PageBlocks`
(admin-kit): paleta, drag (DC-17) y modal generado por `SchemaFields`.
Etiquetas localizables por convención i18n: `blockTypes.{key}`,
`blockFields.{key}`, `blockOptions.{key}.{valor}` (fallback: las del esquema).
**PDF de páginas** (doc 02): el motor registra solo el export `pages` — toda
página publicada con `is_printable` aparece en la sección PDF del admin y se
imprime con la vista `motor::pdf.page` (los bloques imprimibles como
documento de texto; añade la etiqueta `pdfs.types.pages` en typeLabels).
En público: `PageView` pide `/api/pages/{slug}` (slug resuelto en cualquier
locale, redirige a la canónica DC-12), la nav sale de `/api/pages/nav` y la
home del CRM manda si existe. El texto rico se sanea en servidor (DC-09) y el
payload se cachea por (página, locale) con invalidación al editar (DC-10).
`/api/pages/nav` incluye las **hijas publicadas** de cada página raíz: el nav
de la app las pinta como **submenú al hover** (chevron, patrón CDL) en
escritorio y **indentadas** (siempre visibles) en la barra lateral móvil.

### 6bis.1 Plantillas de página

Cada página tiene una **plantilla** (columna `template`); la SPA decide el
layout con esa clave. Añadir una plantilla son tres piezas:

1. **Catálogo** (API): el juego amplía `motor.content.templates` en su
   AppServiceProvider (`config([...templates + ['landing' => 'Portada']])`).
   Con eso ya sale en el select del modal de página del admin
   (`GET /api/admin/pages/templates`; etiqueta localizable por convención
   `pages.templates.{clave}`) y la validación la acepta.
2. **El componente Vue** en `app/src/templates/` (envuelve los bloques por
   slot: `<main class="page-view page-view--landing"><slot /></main>`).
3. **Su entrada** en `app/src/templates/registry.ts`; `templateFor(clave)`
   cae en `default` si la clave no está. `PageView` y `HomeView` envuelven
   los bloques con la plantilla del payload (`data.template`).

El playground trae `landing` (la usa la home demo del seeder): la anchura
del contenido ya la decide **cada bloque** con su campo común `width`, así
que la plantilla solo aporta el aire de portada (más padding vertical en el
primer bloque). Una plantilla es el sitio para diferencias de LAYOUT; la
anchura es cosa de los bloques.

### 6bis.1b Configuración de la web (Fase 5.5)

El admin trae una página **Configuración** (`GET/PUT /api/admin/settings/site`;
se guarda en la tabla `settings` bajo la clave 'site' y la SPA pública lo lee
de **`GET /api/site`** al arrancar):

- **Identidad:** título del sitio (traducible; el `document.title` sale como
  `página · sitio`), descripción (meta por defecto), **logo** (SVG/PNG) y
  **favicon**. El logo SVG viaja **inlineado** en el payload (`logo_inline`) y
  se pinta con `currentColor`: hereda el acento activo (un fichero
  cross-origin no valdría — fetch/mask exigen CORS).
- **Acento:** fijo (un color) o **ALEATORIO estilo CDL**: una lista de colores
  candidatos de la que la SPA sortea uno al cargar **y re-sortea en cada
  navegación** (`router.afterEach` → `site.onNavigate()`, el disparador extra
  que necesita una SPA que no recarga). Los tonos 200–700 se derivan del HEX
  con `color-mix`; el logo SVG se recolorea con cada sorteo.
- **Fuentes:** títulos y texto por separado, del catálogo
  `motor.site.fonts`. Una entrada puede ser una pila CSS a secas (pilas del
  sistema) o `{label, stack, files}` — los `files` son woff2 en
  `public/fonts` del API (el playground registra Inter, Open Sans,
  Montserrat, Roboto, EB Garamond, Lora, Playfair, IM Fell, Italianno y
  JetBrains Mono en su AppServiceProvider) y **se sirven por
  `GET /api/site/fonts/{path}`**, que hereda el CORS del grupo api (los
  webfonts cross-origin lo exigen). La SPA genera los `@font-face` del
  payload (solo se descargan las usadas) y aplica las pilas con
  `--font-headings` / `--font-body`; el admin hace lo mismo para que las
  vistas previas del select se pinten con la fuente real. Además el admin
  puede **subir fuentes propias** (`POST /api/admin/settings/fonts`,
  woff2/woff/ttf/otf): quedan en `custom_fonts` y entran al catálogo como
  una familia más.
- **Pie:** texto del footer (traducible).

En la app, todo vive en `stores/site.ts` (cargar + aplicar + sorteo).

### 6bis.2 Colores e imagen de fondo (patrón CDL)

- **Imagen de fondo por página** (columna `background_image`, subida desde el
  modal de página): la SPA pinta `<PageBackground>` (@edc-motor/ui) — una capa FIJA
  a toda la ventana tras el contenido (`z-index: -1`, cover, `grayscale(60%)`)
  cuya intensidad decide el tema: `--page-bg-opacity` = **0.2 en claro, 0.1
  en oscuro** (variables de `_theme.scss`, el juego puede recalibrarlas).
- **El color de fondo de un bloque NO es opaco**: BlockShell lo aplica como
  tinte `color-mix(in srgb, <color> var(--block-tint), transparent)` con
  `--block-tint` = **20% en claro, 12% en oscuro** — así la imagen de fondo
  se ve a través y el mismo HEX funciona en ambos temas.
- Las **tarjetas** de los bloques (text-card, cta) también son
  semitransparentes (`color-mix($surface 65%)` + `backdrop-filter: blur`).

## 6ter. Usuarios y permisos (Fase 5.6)

El motor separa qué ve cada rol DENTRO del admin con tres **permisos**
(Spatie, entran por el Gate → middleware `can:`):

- **`manage-game`** — entidades del juego, iconos, imágenes PNG y PDF.
- **`manage-web`** — CRM de páginas y Configuración de la web.
- **`manage-users`** — gestión de usuarios.

Reparto por rol en config (`motor.auth.permissions` +
`motor.auth.role_permissions`): admin lleva los tres, **editor solo
`manage-game`**. La sincronía vive en `MotorAuth::syncRolesAndPermissions()`
(la usan `motor:install`, el seeder y los tests — si cambias el reparto,
re-ejecuta `php artisan motor:install`).

Qué protege cada capa:
- **API**: las rutas del motor ya llevan su `can:`; las rutas de TUS
  entidades deben llevar `can:manage-game` (mira `routes/api.php` del
  playground: el grupo admin entero).
- **Admin SPA**: `/auth/me` incluye `permissions`; la nav se filtra con
  `auth.can('manage-…')` y las rutas llevan `meta.permission` (el guard
  redirige al panel si no toca).

**Gestor de usuarios** (`/api/admin/users`, vista Usuarios del admin): tarjetas
en grid (patrón `ManagerCard`, la tarjeta entera selecciona → panel derecho),
listar con búsqueda, crear con rol, editar (contraseña vacía = no cambiar),
verificar/desverificar el email desde el panel (`POST
/admin/users/{id}/toggle-verified`) y borrar. Chips de rol por color: admin
verde, editor azul (`locale-chip is-info`), usuario neutro. Guardas: nadie se
borra a sí mismo ni se cambia su propio rol.

## 6quater. Copias de seguridad (Fase 6)

El motor trae `spatie/laravel-backup` (DC-16) ya configurado: NO hace falta
publicar `config/backup.php`. `MotorBackup::applyConfig()` (lo llama el
provider del motor) deriva todo de `motor.backup`:

```php
'backup' => [
    'disk' => env('MOTOR_BACKUP_DISK', 'backups'), // si no existe en filesystems, se crea local en storage/app/backups
    'keep_days' => env('MOTOR_BACKUP_KEEP_DAYS', 14), // retención de backup:clean
    'include_media' => env('MOTOR_BACKUP_MEDIA', true), // mete storage/app/public en el zip
],
```

Detalles que resuelve el motor:
- **SQLite**: el fichero de la BBDD entra en el zip tal cual (el dump de
  spatie exige el binario `sqlite3`, que no siempre está); mysql/pgsql usan
  el dump normal (necesitan `mysqldump`/`pg_dump` en el servidor).
- **Sin notificaciones por correo** y `backup:list`/`backup:monitor`
  apuntando al disco del motor.

**API + admin** (solo `manage-web`): `GET/POST /api/admin/backups`,
`GET /api/admin/backups/{file}/download`, `DELETE /api/admin/backups/{file}`.
La vista **Copias** del admin crea con un clic, lista con fecha y tamaño, y
descarga/borra desde el panel derecho (descarga autenticada por la API, con
blob).

**Copia automática (se configura desde el admin).** La programa el MOTOR:
la vista Copias trae la tarjeta "Copia automática" (activada, frecuencia
diaria/semanal, día, hora y retención), guardada en la tabla `settings`
(clave `backup`, servicio `BackupSettings`) y leída por
`MotorBackup::schedule()` en cada `schedule:run` — los cambios aplican sin
redeploy. La retención del admin también gobierna `backup:clean` (pisa
`motor.backup.keep_days`, que queda como valor base). API:
`PUT /api/admin/backups/schedule` (la config viaja en el
`GET /api/admin/backups`). En el juego NO declares el backup en
`routes/console.php`; solo necesitas el cron del scheduler y, si quieres,
el resto de tareas:

```php
Schedule::command('pdf:cleanup')->hourly(); // PDFs temporales (doc 02)
```

**Restore** (DC-16, manual): paso a paso en `funcionalidades/06`
(down → reponer BBDD y media → optimize:clear → up). **BBDD grandes**:
`MOTOR_BACKUP_QUEUE=true` y la copia manual va en cola (respuesta 202; la
vista sondea el listado hasta que aparece; hace falta un worker).

## 6quinquies. Web pública: locale, SEO y listados (Fase 6)

**Prefijo de locale (doc 04, DC-03).** El router de la `app` cuelga todo de
`/:locale(es|eu|en)` (más `/_render` a pelo). El guard sincroniza el prefijo
con `stores/locales` (que añade `?locale` a la API) y con vue-i18n (textos de
la propia interfaz, `src/i18n/`). Las URL sin prefijo redirigen al locale
persistido. El `LocaleSelector` cambia de idioma **navegando** (misma ruta,
otro prefijo) y cada vista redirige después a su canónica (DC-12): slug de
página, segmento de sección y slug de entidad en el idioma activo.

**Listados de entidades genéricos.** En `src/entities/registry.ts` cada
sección declara: `endpoint` público, `paths` por locale (segmento de URL),
`titleKey` (i18n) y los componentes `item` (tarjeta del índice) y `detail`
(cuerpo del detalle), ambos con props `{ item, locale }`. Las vistas
genéricas `EntityIndexView`/`EntityDetailView` hacen fetch, canónica y SEO.

**Single estándar (plantilla CDL).** `EntityDetailView` pone la imagen de la
entidad de **fondo de página** (`PageBackground`), un **banner** tintado a lo
ancho (enlace Volver, `h1` con el nombre, subtítulo = descripción sin HTML
recortada, y el botón "Añadir a tu PDF" si la sección es coleccionable) y
debajo monta el componente `detail` de la sección. Ese componente solo pinta
la **ficha**: `.entity-single__layout` (preview | panel con `.entity-single__info`
— filas `dt/dd` — y la descripción rich). Si no hay preview (entidad sin
imagen), el panel ocupa todo el ancho. Ver `CharacterDetail.vue` (carta +
estadísticas) y `HouseDetail.vue` (escudo + color + rejilla de argucias con
su botón de añadir).
El backend expone los endpoints públicos (solo publicados, slug resoluble en
cualquier locale, payload con el mapa `slug` por locale — mismo shape que los
bloques con-datos, p. ej. `renderData($locale) + slugs`).

**SEO.** `useHead` (de `@edc-motor/ui`) fija title, meta description, canonical y
alternates hreflang por ruta (sin dependencias). El **sitemap** lo sirve la
API en `GET /sitemap.xml`: el motor mete las páginas publicadas del CRM y el
juego añade sus entidades en el provider:

```php
Sitemap::add(fn () => self::sitemapEntries(
    Character::published()->get(),
    ['es' => 'personajes', 'eu' => 'pertsonaiak', 'en' => 'characters'], // = entityRegistry
));
```

**Prerender (DC-18).** `npm run build && npm run prerender` (con la API
levantada): el script recorre el sitemap con Chromium y escribe
`dist/<ruta>/index.html` con el HTML ya renderizado (head incluido), y
remata `dist/robots.txt` con la línea `Sitemap:` absoluta (el
`public/robots.txt` base permite todo menos `/_render/`). El hosting
estático debe servir esos ficheros antes del fallback a `index.html`. Env:
`VITE_API_URL` y `MOTOR_CHROME_PATH`. Las vistas esperan `site.load()`
antes de fijar el head: los títulos prerenderizados llevan siempre el
sufijo del sitio.

> Ojo: los slugs de páginas del CRM no deben chocar con los segmentos de los
> listados (p. ej. una página con slug EU `etxeak` taparía el listado de
> casas): el router da prioridad a las secciones.

**Cabecera pública (dos líneas, patrón kontuan + CDL).** Línea 1: marca a la
izquierda y, a la derecha, las acciones en **tres grupos separados por una
línea vertical** (`.site-header__set` / `__sep`): [enlace a
**administración** (icono, solo si `can_access_admin`; URL en
`VITE_ADMIN_URL`) + icono de **Descargas** con el contador] | [**Entrar**
(botón-texto) o chip de usuario] | [selector de **idioma** + **tema**].
Línea 2: la nav **centrada** — páginas raíz del CRM (las que tienen hijas
publicadas llevan chevron y submenú al hover: al elegir una hija el
desplegable se CIERRA y el padre queda coloreado como activo), secciones de
entidades y Descargas (sin color fijo: hover/activo como el resto). En móvil todo (salvo
el logo) pasa a la barra lateral off-canvas (hijas indentadas; **100 % de
ancho** por debajo de `$bp-sm`). El admin tiene el enlace inverso, **"Ver la
web"** (solo icono), en su barra superior (`VITE_APP_URL`), y en estrecho su
barra superior muestra la **marca** (el sidebar está oculto) y, con el
drawer izquierdo abierto, la barra derecha (panel + asa) se esconde. La página ocupa
siempre al menos el alto de la ventana (columna flex): el footer queda
pegado abajo.

**Sesión compartida web <-> admin (handoff).** Las dos SPA viven en orígenes
distintos (localStorage separado), así que los enlaces cruzados **traspasan
la sesión** con un código de un solo uso: el origen pide
`POST /api/auth/handoff` (autenticado → `{ code }`, 60 s en caché) y navega a
`destino/?handoff=CODE`; el guard del destino canjea el código
(`POST /api/auth/handoff/consume`, público y con throttle: el código ES la
credencial) por un **token propio** y limpia la URL. El token Sanctum nunca
viaja en la URL; si el código caducó, se sigue al login normal.

**Errores hacia el frontend.** Los `apiMessage()` de ambas SPA solo confían
en el `message` de los errores **4xx** (textos que el backend traduce a
propósito); un 5xx muestra siempre el mensaje genérico — con `APP_DEBUG`
activo Laravel mete el volcado (SQL, trazas) en la respuesta y eso no debe
verse jamás. En producción, además, `APP_DEBUG=false`. Y las escrituras
oportunistas del motor (p. ej. guardar `users.locale` en login/registro) van
con `rescue()`: una migración pendiente no tumba el login (el detalle queda
en los logs).

**Privacidad.** La web **no usa cookies** — solo localStorage con
almacenamiento estrictamente funcional (idioma, tema, sesión, token de la
colección), así que **no hace falta banner de consentimiento**. En el
**registro**, un aviso breve de protección de datos con **checkbox
obligatorio**: el backend exige `privacy` aceptado
(`'privacy' => ['accepted']` en el register del motor). Y la colección de
PDF vive **en la cuenta, no en el navegador**: al autenticarse con la
cabecera `X-Collection-Token` presente, lo acumulado como invitado (items y
PDF temporales) se **adopta** al usuario (si un item se repite, gana el de
más copias).

**Correos en el idioma del usuario (DC-14).** El registro guarda el locale
activo en `users.locale` (y el login lo actualiza); el User implementa
`HasLocalePreference` (el trait `IsMotorUser` aporta `preferredLocale()`) y
las notificaciones de Laravel (verificación, reset…) salen traducidas — las
cadenas es/eu viven en `packages/core/lang/{es,eu}.json`
(`loadJsonTranslationsFrom`).

## 6sexies. Panel de usuario extensible (Fase 6)

El panel (`/:locale/cuenta`) es un layout con menú lateral
(`AccountLayout`) y **una child route por sección** registrada en
`src/account/registry.ts`. El motor aporta las secciones base — Mis datos y
Contraseña (doc 05) — y cada juego cuelga las suyas añadiendo entradas a
`gameSections`:

```ts
const gameSections: AccountSection[] = [
  {
    key: 'print',
    path: 'imprimir',          // segmento bajo cuenta/
    name: 'account-print',      // nombre de ruta
    titleKey: 'account.sections.print', // etiqueta del menú (i18n)
    component: PrintSection,    // el componente ES la sección
  },
]
```

El router monta las children automáticamente (todas con `meta.auth`). El
playground trae de ejemplo **"Para imprimir"** (doc 02): añade personajes a
la colección temporal (`POST /pdf-collection/items` con `{entity, id,
copies}`), la lista con miniaturas (previews), genera el PDF en cola
(`POST /pdf-collection/generate`, sondeo de `GET /pdf-collection/pdfs/{id}`
hasta `ready`) y lo descarga por su `url`. Recuerda un worker de colas en
producción (`php artisan queue:work`).

## 7. Checklist para una entidad nueva

Backend:
- [ ] Migración (json traducibles, `is_published`, `datetimes()`/`softDeletesDatetime()`; FK si hay relación).
- [ ] Modelo con los traits que necesite (`HasFilters`, `HasPublishedState`, `HasImage`, `ResolvesBySlug`, `HasTranslations`, `HasTranslatableSlug`, `SoftDeletes`).
- [ ] `$fillable`, `$translatable`, `$searchable`, `casts`, `getSlugOptions`.
- [ ] Relaciones (`belongsTo`/`hasMany`) y, si aplica, endpoint `options` para selectores.
- [ ] Campos calculados en `saving()`/accessor (no recibidos del cliente).
- [ ] Controlador (index con `filter`, CRUD por slug, restore/force por id, toggle).
- [ ] Resource (incluye relaciones con `whenLoaded`).
- [ ] Rutas admin (+ públicas si aplica).
- [ ] `php artisan migrate`.

Frontend admin:
- [ ] i18n en los 3 locales (routes, breadcrumbs, nav, sección del modelo).
- [ ] Rutas localizadas en `i18n-paths.ts` (listado + single `:slug`).
- [ ] Enlace en `App.vue`.
- [ ] `XListView.vue` (FilterBar + BaseTabs + BaseGrid + EntityCard + EmptyState; tarjetas clicables → single).
- [ ] `XFormModal.vue` (EditModal + campos; selectores/relaciones y campos calculados si aplica).
- [ ] `XSingleView.vue` + `XCard.vue` (detalle + modo carta) si la entidad tiene single.
- [ ] Validación cliente + `fieldErrors` + toast genérico.
- [ ] SCSS de la vista en `views/_<modelo>.scss`.

Render a PNG (si la entidad se imprime/expone como carta):
- [ ] Columna `preview_image` + contrato/trait + `Previews::register(...)`.
- [ ] Componente visual en `packages/shared` + entrada en `render/registry.ts`.
- [ ] Invalidar al subir imagen; `previews` en el Resource; `PreviewPanel` en el single.
- [ ] `previewLabel()` en el modelo (etiqueta del gestor); la vista "Imágenes" ya lista el tipo sola.

PDF (si la entidad entra en algún export):
- [ ] Export en `api/app/Pdf/` (colección por entidad con `sources()`, o global) + `Pdfs::register(...)`.
- [ ] Etiqueta del export en `typeLabels` de la vista PDF (i18n `pdfs.types.*`).
- [ ] Tamaño de pieza correcto en `motor.pdf.layouts` (Magic 63×88 por defecto) y `previewSize()` proporcional.

---

## 8. Convenciones que aplican siempre

- **DC-24** — código/tablas en inglés; `datetimes()`/`softDeletesDatetime()`.
- **DC-25** — iconos de UI con `@lucide/vue`.
- **DC-26** — mobile-first; nada más ancho que el 100%.
- **DC-27** — SCSS en ficheros globales (BEM), nada de `<style>` en `.vue`.
- **DC-28** — buscar antes en kontuan (y CDL para cartas); copiar y adaptar.
- **DC-29** — i18n de toda la app + rutas y slugs traducibles.
- **DC-30** — index sin tablas: grid de tarjetas.
- **DC-31** — altas/ediciones en modal (no rutas).
- **DC-32** — WYSIWYG TipTap + biblioteca de iconos del motor.

Detalle de cada componente en [`guia-de-componentes.md`](guia-de-componentes.md).
