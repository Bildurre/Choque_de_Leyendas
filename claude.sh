#!/bin/sh
#
# Flujo de ramas de Claude:
#   sh claude.sh --start  claude/nombre-de-rama   (traerla, o crearla si no existe)
#   sh claude.sh --finish claude/nombre-de-rama   (mergear en main y borrarla)
#   sh claude.sh --finish claude/nombre-de-rama --motor 0.4.26
#     (mergear + update-motor + commit de manifiestos, y PUSHEAR TODO JUNTO:
#      así el CI nunca ve el cascarón nuevo con los paquetes viejos)

set -e

BRANCH="$2"

if [ -z "$1" ] || [ -z "$BRANCH" ]; then
  echo "Uso:"
  echo "  sh claude.sh --start claude/nombre-de-rama"
  echo "  sh claude.sh --finish claude/nombre-de-rama [--motor X.Y.Z]"
  exit 1
fi

MOTOR_VERSION=""
if [ "$3" = "--motor" ]; then
  if [ -z "$4" ]; then
    echo "Falta la versión tras --motor (ej: --motor 0.4.26)" >&2
    exit 1
  fi
  MOTOR_VERSION="$4"
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
    # Con --motor, los manifiestos suben ANTES del push: el merge y el
    # "Motor a X" viajan juntos y el CI solo evalúa el estado final.
    if [ -n "$MOTOR_VERSION" ]; then
      echo "Actualizando el motor a $MOTOR_VERSION antes de pushear..."
      ./update-motor.sh "$MOTOR_VERSION"
      git add -A
      git commit -m "Motor a $MOTOR_VERSION"
    fi
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
