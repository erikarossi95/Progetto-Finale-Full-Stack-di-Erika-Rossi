import { defineStore } from 'pinia'
import api, { getStoredToken, setStoredToken } from '@/api/axios'

// Store di autenticazione: utente corrente, token, sessione persistente.
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: getStoredToken(),
  }),
  getters: {
    isAuthenticated: (state) => !!state.token && !!state.user,
  },
  actions: {
    // Salva token (in stato + localStorage) e utente.
    setSession(user, token) {
      this.user = user
      this.token = token
      setStoredToken(token)
    },

    // Pulisce la sessione (logout o token invalido).
    clearSession() {
      this.user = null
      this.token = null
      setStoredToken(null)
    },

    async register({ name, email, password }) {
      const { data } = await api.post('/register', { name, email, password })
      this.setSession(data.data.user, data.data.token)
      return data.data.user
    },

    async login({ email, password }) {
      const { data } = await api.post('/login', { email, password })
      this.setSession(data.data.user, data.data.token)
      return data.data.user
    },

    async logout() {
      // Il JWT è stateless: chiamiamo l'endpoint per completezza, poi
      // il logout reale avviene cancellando il token lato client.
      try {
        await api.post('/logout')
      } catch {
        /* ignora errori: il logout client procede comunque */
      }
      this.clearSession()
    },

    // Ripristina la sessione al refresh: se c'è un token, valida con /me.
    async fetchMe() {
      if (!this.token) return null
      try {
        const { data } = await api.get('/me')
        this.user = data.data.user
        return this.user
      } catch {
        this.clearSession()
        return null
      }
    },

    async updateProfile(payload) {
      const { data } = await api.put('/profile', payload)
      this.user = data.data.user
      return this.user
    },
  },
})
