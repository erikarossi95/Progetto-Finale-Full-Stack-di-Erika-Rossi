import { defineStore } from 'pinia'
import api, { parseApiError } from '@/api/axios'
import { useUiStore } from '@/stores/ui'

const KEY = 'snaply_liked'

function load() {
  try {
    return new Set(JSON.parse(localStorage.getItem(KEY) || '[]'))
  } catch {
    return new Set()
  }
}

// Store dei "cuori". Il like è pubblico (nessun account): per evitare doppi
// like dallo stesso dispositivo memorizziamo gli id già piaciuti in localStorage.
// Il conteggio autorevole vive sul backend; qui aggiorniamo in modo ottimistico
// l'oggetto foto (photo.likes) passato dalle view.
export const useLikesStore = defineStore('likes', {
  state: () => ({
    likedIds: load(),
  }),
  getters: {
    isLiked: (state) => (id) => state.likedIds.has(id),
  },
  actions: {
    persist() {
      localStorage.setItem(KEY, JSON.stringify([...this.likedIds]))
    },

    // Toggle like/unlike con UI ottimistica e rollback in caso d'errore.
    async toggle(photo) {
      const wasLiked = this.likedIds.has(photo.id)
      // Ottimistico
      if (wasLiked) {
        this.likedIds.delete(photo.id)
        photo.likes = Math.max(0, (photo.likes || 1) - 1)
      } else {
        this.likedIds.add(photo.id)
        photo.likes = (photo.likes || 0) + 1
      }
      this.persist()

      try {
        const { data } = wasLiked
          ? await api.delete(`/public/photos/${photo.id}/like`)
          : await api.post(`/public/photos/${photo.id}/like`)
        // Allinea al conteggio reale del server.
        photo.likes = data.data.likes
      } catch (e) {
        // Rollback dello stato ottimistico
        if (wasLiked) {
          this.likedIds.add(photo.id)
          photo.likes = (photo.likes || 0) + 1
        } else {
          this.likedIds.delete(photo.id)
          photo.likes = Math.max(0, (photo.likes || 1) - 1)
        }
        this.persist()
        // Feedback (es. 429 rate limit)
        useUiStore().error(parseApiError(e, 'Impossibile aggiornare il cuore').message)
      }
    },

    // Doppio-tap: mette il like solo se non già presente (mai unlike).
    async likeOnce(photo) {
      if (!this.likedIds.has(photo.id)) await this.toggle(photo)
    },
  },
})
