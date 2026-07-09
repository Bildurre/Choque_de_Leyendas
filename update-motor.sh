#!/usr/bin/env bash
#
# Actualiza los paquetes del motor (edc-motor/core, @edc-motor/ui,
# @edc-motor/admin-kit) a la versión indicada.
#
# Uso: ./update-motor.sh 0.4.0

set -euo pipefail

VERSION="${1:-}"

if [[ -z "$VERSION" ]]; then
  echo "Uso: $0 <version>   (ej: $0 0.4.0)" >&2
  exit 1
fi

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Versión no válida: '$VERSION' (formato esperado: X.Y.Z, ej. 0.4.0)" >&2
  exit 1
fi

# Ejecutar siempre desde la raíz del repo (donde vive este script)
cd "$(dirname "$0")"

echo "==> Backend: edc-motor/core ^$VERSION"
(
  cd api
  composer require "edc-motor/core:^$VERSION" --update-with-all-dependencies
  php artisan migrate
  php artisan optimize:clear
)

echo
echo "==> Frontend: @edc-motor/ui y @edc-motor/admin-kit ^$VERSION"
npm install "@edc-motor/ui@^$VERSION" "@edc-motor/admin-kit@^$VERSION"

echo
echo "✔ Paquetes actualizados a ^$VERSION"
echo
echo "Recuerda revisar el CHANGELOG del motor por si esta versión trae"
echo "'migración del cascarón' (archivos a copiar de plantilla/)."
echo "Reinicia el dev server o lanza 'npm run build'."