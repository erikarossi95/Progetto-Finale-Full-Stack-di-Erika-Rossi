import { defineStore } from 'pinia'

let nextId = 1

// Store dei toast: feedback di successo/errore/info per ogni azione API.
export const useUiStore = defineStore('ui', {
  state: () => ({
    /** @type {Array<{id:number,type:string,message:string}>} */
    toasts: [],
  }),
  actions: {
    notify({ type = 'info', message, timeout = 4000 }) {
      const id = nextId++
      this.toasts.push({ id, type, message })
      if (timeout > 0) {
        setTimeout(() => this.dismiss(id), timeout)
      }
      return id
    },
    success(message) {
      return this.notify({ type: 'success', message })
    },
    error(message) {
      return this.notify({ type: 'error', message })
    },
    info(message) {
      return this.notify({ type: 'info', message })
    },
    dismiss(id) {
      this.toasts = this.toasts.filter((t) => t.id !== id)
    },
  },
})
