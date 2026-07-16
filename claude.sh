#!/bin/sh
#
# Flujo de ramas de Claude:
#   sh claude.sh --start  claude/nombre-de-rama   (traerla, o crearla si no existe)
#   sh claude.sh --finish claude/nombre-de-rama   (mergear en main y borrarla)

set -e

BRANCH="$2"

if [ -z "$1" ] || [ -z "$BRANCH" ]; then
  echo "Uso:"
  echo "  sh claude.sh --start claude/nombre-de-rama"
  echo "  sh claude.sh --finish claude/nombre-de-rama"
  exit 1
fi

case "$1" in
  --start)
    git fetch origin
    if git rev-parse --verify "origin/$BRANCH" >/dev/null 2>&1; then
      echo "Obteniendo rama $BRANCH..."
      git checkout -B "$BRANCH" "origin/$BRANCH"
    else
      echo "La rama no existe en remoto: la creo desde main..."
      git checkout main
      git pull origin main
      git checkout -b "$BRANCH"
      git push -u origin "$BRANCH"
    fi
    echo "Listo. Estás en $BRANCH"
    ;;

  --finish)
    echo "Mergeando $BRANCH en main..."
    git fetch origin
    git rev-parse --verify "origin/$BRANCH" >/dev/null 2>&1 || {
      echo "La rama origin/$BRANCH no existe" >&2; exit 1; }
    git checkout main
    git pull origin main
    # Se mergea la punta REMOTA: da igual si la copia local está desfasada.
    git merge "origin/$BRANCH"
    git push origin main
    echo "Eliminando rama $BRANCH..."
    git branch -D "$BRANCH" 2>/dev/null || true
    git push origin --delete "$BRANCH"
    echo "Listo. Rama $BRANCH mergeada en main y eliminada."
    ;;

  *)
    echo "Opción no reconocida: $1"
    echo "Usa --start o --finish"
    exit 1
    ;;
esac
