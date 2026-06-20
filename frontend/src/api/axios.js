import axios from 'axios'

// Istanza Axios centralizzata verso il backend Snaply.
const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api',
})

const TOKEN_KEY = 'snaply_token'

export function getStoredToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function setStoredToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

// --- Request interceptor: allega il bearer token se presente ---
api.interceptors.request.use((config) => {
  const token = getStoredToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// --- Response interceptor: su 401 svuota la sessione e rimanda al login ---
// L'handler effettivo (logout + redirect) viene iniettato da main.js per
// evitare dipendenze circolari tra axios, store e router.
let onUnauthorized = null
export function setUnauthorizedHandler(fn) {
  onUnauthorized = fn
}

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    // Ignora i 401 della pagina di login (credenziali errate gestite a parte).
    const url = error.config?.url || ''
    const isAuthAttempt = url.includes('/login') || url.includes('/register')
    if (status === 401 && !isAuthAttempt && typeof onUnauthorized === 'function') {
      onUnauthorized()
    }
    return Promise.reject(error)
  }
)

/**
 * Estrae un messaggio leggibile dall'envelope d'errore del backend.
 * Ritorna { message, fields } dove fields è la mappa per-campo (può essere {}).
 */
export function parseApiError(error, fallback = 'Si è verificato un errore') {
  const err = error.response?.data?.error
  return {
    message: err?.message || fallback,
    fields: err?.fields || {},
    code: err?.code || null,
  }
}

export default api
