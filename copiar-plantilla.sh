#!/usr/bin/env bash
#
# Copia archivos del cascarón desde plantilla/ del monorepo del motor a este
# juego, respetando la misma ruta relativa.
#
# Uso:
#   ./copiar-plantilla.sh <ruta-relativa-a-plantilla>...
#   ./copiar-plantilla.sh -t v0.4.0 <ruta>...   # desde un tag concreto
#
# Ejemplos:
#   ./copiar-plantilla.sh admin/src/views/pages/PageSingleView.vue
#   ./copiar-plantilla.sh -t v0.4.0 app/src/assets/scss/components/_app-header.scss
#
# El motor se busca en ../boardgame_motor (hermano de este repo). Si lo tienes
# en otra ruta: MOTOR_DIR=/otra/ruta ./copiar-plantilla.sh ...

set -euo pipefail

MOTOR_DIR="${MOTOR_DIR:-../boardgame_motor}"

TAG=""
while getopts "t:" opt; do
  case $opt in
    t) TAG="$OPTARG" ;;
    *) exit 1 ;;
  esac
done
shift $((OPTIND - 1))

if [[ $# -eq 0 ]]; then
  echo "Uso: $0 [-t vX.Y.Z] <ruta-relativa-a-plantilla>..." >&2
  exit 1
fi

# Ejecutar siempre desde la raíz del juego (donde vive este script)
cd "$(dirname "$0")"

if [[ ! -d "$MOTOR_DIR/plantilla" ]]; then
  echo "No encuentro plantilla/ en '$MOTOR_DIR'." >&2
  echo "Clona el motor ahí o indica la ruta: MOTOR_DIR=/ruta/al/motor $0 ..." >&2
  exit 1
fi

for ruta in "$@"; do
  # Aviso si el archivo tiene cambios locales sin commitear (se pisarían)
  if [[ -n "$(git status --porcelain -- "$ruta" 2>/dev/null)" ]]; then
    echo "⚠ $ruta tiene cambios sin commitear en este repo; se van a sobreescribir" >&2
  fi

  mkdir -p "$(dirname "$ruta")"
  if [[ -n "$TAG" ]]; then
    git -C "$MOTOR_DIR" show "$TAG:plantilla/$ruta" > "$ruta"
  else
    cp "$MOTOR_DIR/plantilla/$ruta" "$ruta"
  fi
  echo "✔ $ruta"
done

echo
echo "Hecho. Revisa con 'git diff' qué ha cambiado antes de commitear:"
echo "si habías personalizado alguno de estos archivos, ahí lo verás."