// Dado de acción del juego. Las caras NO son uniformes: cada dado tiene
// 3 caras rojas, 2 verdes y 1 azul (documentado en el CRM del proyecto viejo,
// bloque "Tablas de probabilidades": sus porcentajes salen exactamente de
// P(R)=3/6, P(G)=2/6, P(B)=1/6 con 5 dados). Todo "tirar dado" pasa por
// rollDie(): una única fuente de verdad de caras y aleatoriedad.

export const DIE_COLORS = ['red', 'green', 'blue'] as const
export type DieColor = (typeof DIE_COLORS)[number]

/** Las 6 caras del dado físico: 3R / 2G / 1B. */
const FACES: readonly DieColor[] = ['red', 'red', 'red', 'green', 'green', 'blue']

/** Entero uniforme en [0, max) con crypto si está disponible. */
function randomInt(max: number): number {
  if (typeof crypto !== 'undefined' && 'getRandomValues' in crypto) {
    // Rechazo del resto: sin sesgo aunque 2^32 no sea múltiplo de max.
    const limit = Math.floor(0xffffffff / max) * max
    const buffer = new Uint32Array(1)
    let value: number
    do {
      crypto.getRandomValues(buffer)
      value = buffer[0]
    } while (value >= limit)
    return value % max
  }
  return Math.floor(Math.random() * max)
}

/** Tira un dado de acción y devuelve el color de la cara. */
export function rollDie(): DieColor {
  return FACES[randomInt(FACES.length)]
}

/** Baraja los índices [0, n) y devuelve los `count` primeros (sin repetir). */
export function pickRandomIndices(n: number, count: number): number[] {
  const indices = Array.from({ length: n }, (_, i) => i)
  for (let i = indices.length - 1; i > 0; i--) {
    const j = randomInt(i + 1)
    ;[indices[i], indices[j]] = [indices[j], indices[i]]
  }
  return indices.slice(0, Math.max(0, Math.min(count, n)))
}
