// Helper per costruire URL coerenti tra frontend e backend.

const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'

/**
 * Origin del backend (senza il suffisso /api), usato per i media:
 * es. VITE_API_BASE_URL=http://localhost:8000/api → http://localhost:8000
 */
export function mediaBaseUrl() {
  return API_BASE.replace(/\/api\/?$/, '')
}

/** URL pubblico completo della pagina evento per gli invitati (/e/:slug). */
export function publicEventUrl(slug) {
  return `${window.location.origin}/e/${slug}`
}

/** URL assoluto della copertina (path relativo dal backend) o null. */
export function coverUrl(path) {
  return path ? mediaBaseUrl() + path : null
}
