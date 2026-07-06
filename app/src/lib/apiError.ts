// Mensaje de error de la API para mostrar al usuario. Solo se confía en el
// `message` de los errores 4xx (validación, credenciales… — textos que el
// backend traduce a propósito): un 5xx puede llevar el volcado del servidor
// (SQL, trazas) si APP_DEBUG está activo, y eso NUNCA debe verse en el
// frontend. Para esos, siempre el fallback genérico.

interface ApiErrorShape {
  response?: { status?: number; data?: { message?: string } }
}

export function apiMessage(e: unknown, fallback: string): string {
  const response = (e as ApiErrorShape)?.response
  const status = response?.status ?? 0
  if (status < 400 || status >= 500) return fallback

  const message = response?.data?.message
  return typeof message === 'string' && message !== '' ? message : fallback
}
