# choque-de-leyendas

Web del juego, montada sobre **EdC Motor** (`edc-motor/core` ^0.3.0 de
Packagist; `@edc-motor/ui` y `@edc-motor/admin-kit` de npmjs). Generado con
`tools/crear-juego.sh` del motor: nace con la infraestructura funcionando
(auth, páginas, configuración, PDF, copias, usuarios) y **sin entidades**:
las de tu juego se crean siguiendo las guías.

## Estructura

```
api/               # Laravel + edc-motor/core
admin/             # SPA de administración (@edc-motor/admin-kit)
app/               # web pública (@edc-motor/ui)
packages/shared/   # tus cartas y tipos, compartidos entre admin, app y render PNG
```

## Arranque

```bash
# 1. Backend
cd api
composer install
npm install                 # puppeteer (render de PNG con Browsershot)
cp .env.example .env        # revisa BBDD, colas, URLs y MOTOR_CHROME_PATH
php artisan key:generate
php artisan migrate --force
php artisan motor:install   # roles y permisos base
php artisan db:seed         # usuarios demo + home mínima + configuración

# 2. Frontends (desde la raíz del juego)
cd ..
npm install
npm run dev                 # app :5173 · admin :5174 · api :8010 · queue
```

Credenciales demo del seeder: `admin@edc.test` / `editor@edc.test` /
`user@edc.test`, contraseña `password`.

> Si puppeteer no descarga Chrome (o quieres usar el del sistema), fija
> `MOTOR_CHROME_PATH` en `api/.env` (hay un `npm run chrome:install`
> dentro de `api/`).

## Primera entidad

Sigue `documentacion/guia-como-montar-una-web.md` (§7: checklist de una
entidad completa — migración, modelo, API, carta en `packages/shared`,
vistas de admin y app, previews/PDF y seeder). El ejemplo vivo completo es
el `playground/` del monorepo del motor.

## Actualizar el motor

Lee los `CHANGELOG.md` del motor y:

```bash
cd api && composer update edc-motor/core
cd .. && npm update @edc-motor/ui @edc-motor/admin-kit
```

## Gates (desde la raíz)

`npm run lint` · `npm run type-check` · `npm run build` ·
`npm run lint:php` · `npm run test:api`
