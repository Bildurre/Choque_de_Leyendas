# Guía: cómo arrancar un juego nuevo sobre el motor

Objetivo: crear el repo de un juego nuevo que **instale el motor por versión**
y tenga admin + web pública funcionando **en menos de un día**. Esta guía
cubre el arranque del repo y el consumo por versión; el detalle de cada pieza
(entidades, bloques, PNG, PDF…) está en `guia-como-montar-una-web.md` y
`guia-de-componentes.md`.

## 1. Cómo se distribuye el motor (DC-33)

El motor es **open source (GPL-3.0)** y se instala desde los registros
públicos, como cualquier paquete. Versión **de tren**: los tres paquetes
comparten número y se etiquetan juntos (`vX.Y.Z` en el monorepo):

| Paquete | Qué es | Se instala con |
|---|---|---|
| `edc-motor/core` | Backend Laravel (provider, migraciones, API) | Composer (Packagist) |
| `@edc-motor/ui` | Componentes públicos Vue + SCSS (paquete **fuente**) | npm (npmjs) |
| `@edc-motor/admin-kit` | Kit del admin (paquete **fuente**, depende de `@edc-motor/ui`) | npm (npmjs) |

Los paquetes npm son **fuente** (`main: src/index.ts`): los compila la app
del juego con Vite (por eso necesitan `sass-embedded` y `@vitejs/plugin-vue`
en el consumidor; no hay paso de build en el motor).

**Actualizar de versión** (leer antes los `CHANGELOG.md` del motor):

```bash
cd api && composer update edc-motor/core
cd .. && npm update @edc-motor/ui @edc-motor/admin-kit
```

## 2. Esqueleto del repo del juego

**La forma rápida es `tools/crear-juego.sh`** (vive en el monorepo del
motor): copia la **plantilla mínima** (`plantilla/`) — la infraestructura
funcionando y **sin entidades demo** — y deja las dependencias apuntando a
los registros. No hace falta tener el motor al lado después:

```bash
git clone https://github.com/bildurre/boardgame_motor.git   # una vez, donde sea
boardgame_motor/tools/crear-juego.sh mi-juego
cd mi-juego                                                  # y sigue su README.md
```

La estructura resultante:

```
mi-juego/
├── api/                    # Laravel (edc-motor/core por Composer)
├── admin/                  # SPA de administración (@edc-motor/admin-kit)
├── app/                    # web pública (@edc-motor/ui)
├── packages/shared/        # tus cartas y tipos compartidos (@juego/shared)
└── package.json            # workspaces npm: packages/shared, admin, app
```

Lo que trae de fábrica: auth completo (registro, verificación por email,
reset, SSO web↔admin), CRM de páginas con una home mínima, configuración de
la web (título, logo, fuentes, acento), gestores de imágenes/PDF/iconos,
descargas públicas con colección para imprimir, panel de usuario, copias de
seguridad, usuarios demo (`admin@edc.test` / `password`) y CI. Lo que NO
trae: entidades — las de tu juego se crean con la guía (§7 de la guía de la
web), usando el `playground/` del monorepo como ejemplo vivo completo.

`packages/shared` (`@juego/shared`) existe porque **las cartas se pintan
igual** en el admin, en la web y en el render a PNG (doc 01): componentes de
carta + tipos TS viven una sola vez. La plantilla lo deja vacío con los tipos
base (`EntityBase`, `Translations`).

## 3. Backend (`api/`)

El `composer.json` generado ya exige el motor por versión, sin repositorios
extra:

```json
{
  "require": { "edc-motor/core": "^0.2.0" }
}
```

```bash
cd api
composer install
php artisan vendor:publish --tag=motor-config   # config/motor.php (ya publicado en la plantilla)
php artisan migrate             # migraciones del motor + las tuyas
php artisan motor:install       # roles base (admin, editor, user) y permisos
php artisan db:seed             # usuarios demo + home mínima + configuración
```

En `config/motor.php` configura locales de contenido, colas, PDF (layouts),
backup y `frontend` (URLs de app/admin para correos y render). `.env` mínimo
para desarrollo: SQLite o MySQL, `QUEUE_CONNECTION=sync`, Mailpit
(`MAIL_MAILER=smtp`, puerto 1025) — ver §1.7 de la guía de la web para colas
en producción.

Lo que aporta el juego:

- **Modelos/migraciones/controladores/resources** de sus entidades
  (checklist §7 de `guia-como-montar-una-web.md`).
- **Registros** en `app/Providers/AppServiceProvider.php` (la plantilla trae
  los huecos comentados): `Previews::register(...)` (PNG),
  `Pdfs::layout(...)` y `Pdfs::register(...)` (exports),
  `Blocks::register(...)` (bloques con-datos) y `Sitemap::add(...)` (SEO).
- **Seeder demo** con datos de todas las piezas (regla de la casa: todo lo
  que se genere, al seeder).

## 4. Frontends (`admin/` y `app/`)

Ambos son apps Vite + Vue 3 + TS normales. El `package.json` raíz del juego
trae los paquetes por versión:

```json
{
  "dependencies": {
    "@edc-motor/ui": "^0.2.0",
    "@edc-motor/admin-kit": "^0.2.0"
  }
}
```

y cada `vite.config.ts` carga el SCSS del motor desde `node_modules` (los
paquetes son fuente):

```ts
css: {
  preprocessorOptions: {
    scss: {
      additionalData: '@use "tokens" as *;\n',
      loadPaths: [
        fileURLToPath(new URL('../node_modules/@edc-motor/ui/scss', import.meta.url)),
        fileURLToPath(new URL('../packages/shared/scss', import.meta.url)),
        fileURLToPath(new URL('./src/assets/scss', import.meta.url)),
      ],
    },
  },
},
```

Qué rellena el juego (la plantilla trae los registros vacíos y comentados;
el playground del monorepo es el espejo completo):

- **admin**: un enlace de nav + rutas localizadas (`i18n-paths.ts`) + vistas
  de listado/single por entidad, modales de formulario, i18n ×3; los
  gestores (Imágenes, PDF, Páginas, Configuración, Usuarios, Copias, Iconos)
  son del kit y ya están montados.
- **app**: `entities/registry.ts` (secciones públicas),
  `blocks/registry.ts` (bloques con-datos), `render/registry.ts` (PNG),
  componentes de tarjeta y detalle, i18n ×3, prerender (`npm run prerender`).
- **packages/shared**: componentes de carta + tipos de tus entidades.

## 5. Checklist del primer día

- [ ] Repo generado con `tools/crear-juego.sh`; `git init` + primer commit.
- [ ] `api/` instala `edc-motor/core` de Packagist; `migrate` +
      `motor:install` + `db:seed` OK.
- [ ] `admin/` y `app/` compilan (`npm run build`) con `@edc-motor/*` de npmjs.
- [ ] Login en el admin y home mínima visible en los 3 locales.
- [ ] Primera entidad de juego completa (checklist §7 de la guía de la web).
- [ ] Seeder demo actualizado; gates en verde (`lint`, `type-check`, `build`,
      `lint:php`, `test:api`).

## 6. Versionar y publicar el motor (para quien lo mantiene)

1. Actualiza los `CHANGELOG.md` (raíz + 3 paquetes) con lo nuevo.
2. Sube la versión **en los 4 sitios** (raíz `package.json`, `packages/ui`,
   `packages/admin-kit`, `packages/core/composer.json`).
3. Gates en verde (`lint`, `type-check`, `build`, `lint:php`, `test:api`).
4. Tag y push: `git tag v0.3.0 && git push origin v0.3.0`.
5. El workflow `publicar.yml` hace el resto: publica `@edc-motor/ui` y
   `@edc-motor/admin-kit` en npmjs y espeja `packages/core` (con el tag) al
   repo [`bildurre/edc-core`](https://github.com/bildurre/edc-core), del que
   Packagist se actualiza solo.

Mientras estemos en `0.x`, una versión **menor** puede romper API (SemVer);
los juegos actualizan leyendo el changelog. La prueba de consumo local
(`tools/consumo-externo/probar-consumo.sh`) puede correrse antes de etiquetar.
