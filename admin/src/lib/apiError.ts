// Extrae los errores de validación (422) por campo, ya traducidos por el
// backend. Para cualquier otro error se muestra un mensaje genérico (nunca el
// volcado del servidor: SQL, trazas, etc.).

interface ApiErrorShape {
  response?: {
    status?: number
    data?: { errors?: Record<string, unknown> }
  }
}

export function fieldErrors(e: unknown): Record<string, string> {
  const err = e as ApiErrorShape
  const errs = err?.response?.data?.errors
  if (err?.response?.status !== 422 || !errs) return {}
  const out: Record<string, string> = {}
  for (const [key, msgs] of Object.entries(errs)) {
    out[key] = Array.isArray(msgs) ? String(msgs[0]) : String(msgs)
  }
  return out
}

export function isValidationError(e: unknown): boolean {
  return (e as ApiErrorShape)?.response?.status === 422
}

/**
 * Mensaje de error de la API (message traducido) o el fallback dado. Solo se
 * confía en los 4xx: un 5xx puede llevar el volcado del servidor (SQL,
 * trazas) con APP_DEBUG activo y NUNCA debe verse en el frontend.
 */
export function apiMessage(e: unknown, fallback: string): string {
  const response = (e as ApiErrorShape & { response?: { data?: { message?: string } } })?.response
  const status = response?.status ?? 0
  if (status < 400 || status >= 500) return fallback

  const message = response?.data?.message
  return typeof message === 'string' && message !== '' ? message : fallback
}
