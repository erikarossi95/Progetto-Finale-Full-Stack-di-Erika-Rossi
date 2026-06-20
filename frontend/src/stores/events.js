import { defineStore } from 'pinia'
import api from '@/api/axios'

// Store eventi: lista dell'organizzatore + evento corrente (con foto).
export const useEventsStore = defineStore('events', {
  state: () => ({
    events: [],
    currentEvent: null,
    loading: false,
  }),
  actions: {
    async fetchEvents() {
      this.loading = true
      try {
        const { data } = await api.get('/events')
        this.events = data.data.events
      } finally {
        this.loading = false
      }
    },

    async fetchEvent(id) {
      this.loading = true
      try {
        const { data } = await api.get(`/events/${id}`)
        this.currentEvent = data.data.event
        return this.currentEvent
      } finally {
        this.loading = false
      }
    },

    // Pagina successiva di foto per il dettaglio (paginazione server).
    async fetchMorePhotos(id, page) {
      const { data } = await api.get(`/events/${id}/photos`, { params: { page } })
      if (this.currentEvent?.id === id) {
        this.currentEvent.photos = [...(this.currentEvent.photos || []), ...data.data.photos]
        this.currentEvent.photo_count = data.data.total
      }
      return data.data
    },

    async createEvent(payload) {
      const { data } = await api.post('/events', payload)
      const event = data.data.event
      this.events.unshift({ ...event, photo_count: event.photo_count ?? 0 })
      return event
    },

    async updateEvent(id, payload) {
      const { data } = await api.put(`/events/${id}`, payload)
      const updated = data.data.event
      // Aggiorna sia il dettaglio sia l'eventuale voce in lista.
      if (this.currentEvent?.id === id) {
        this.currentEvent = { ...this.currentEvent, ...updated }
      }
      const idx = this.events.findIndex((e) => e.id === id)
      if (idx !== -1) this.events[idx] = { ...this.events[idx], ...updated }
      return updated
    },

    async deleteEvent(id) {
      await api.delete(`/events/${id}`)
      this.events = this.events.filter((e) => e.id !== id)
      if (this.currentEvent?.id === id) this.currentEvent = null
    },

    // Carica/sostituisce la copertina (multipart). Ritorna l'evento aggiornato.
    async uploadCover(id, file, onProgress) {
      const fd = new FormData()
      fd.append('file', file)
      const { data } = await api.post(`/events/${id}/cover`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (e) => {
          if (onProgress && e.total) onProgress(Math.round((e.loaded / e.total) * 100))
        },
      })
      this.applyEventUpdate(id, data.data.event)
      return data.data.event
    },

    async deleteCover(id) {
      const { data } = await api.delete(`/events/${id}/cover`)
      this.applyEventUpdate(id, data.data.event)
      return data.data.event
    },

    // Avatar evento (immagine). L'emoji passa invece dal normale updateEvent.
    async uploadAvatar(id, file) {
      const fd = new FormData()
      fd.append('file', file)
      const { data } = await api.post(`/events/${id}/avatar`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      this.applyEventUpdate(id, data.data.event)
      return data.data.event
    },

    async deleteAvatar(id) {
      const { data } = await api.delete(`/events/${id}/avatar`)
      this.applyEventUpdate(id, data.data.event)
      return data.data.event
    },

    // Sincronizza dettaglio + voce in lista con la versione aggiornata.
    applyEventUpdate(id, updated) {
      if (this.currentEvent?.id === id) {
        this.currentEvent = { ...this.currentEvent, ...updated }
      }
      const idx = this.events.findIndex((e) => e.id === id)
      if (idx !== -1) this.events[idx] = { ...this.events[idx], ...updated }
    },

    async deletePhoto(photoId) {
      await api.delete(`/photos/${photoId}`)
      if (this.currentEvent) {
        this.currentEvent.photos = (this.currentEvent.photos || []).filter((p) => p.id !== photoId)
        this.currentEvent.photo_count = Math.max(0, (this.currentEvent.photo_count || 1) - 1)
      }
    },
  },
})
