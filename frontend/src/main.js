import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './assets/main.css'

import { setUnauthorizedHandler } from '@/api/axios'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { focusTrap } from '@/directives/focusTrap'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.directive('focus-trap', focusTrap)

// Rispetto di prefers-reduced-motion: se l'utente preferisce ridurre il
// movimento, NON registriamo il motion plugin. Le direttive v-motion restano
// inerti e tutti i contenuti sono subito visibili, senza animazioni.
const reduceMotion =
  typeof window !== 'undefined' && !!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

// Esposto ai componenti per disattivare le transizioni di pagina.
app.provide('reduceMotion', reduceMotion)

const auth = useAuthStore()
const ui = useUiStore()

// Interceptor 401 → sessione scaduta: pulisci e rimanda al login.
setUnauthorizedHandler(() => {
  if (auth.isAuthenticated || auth.token) {
    auth.clearSession()
    ui.error('Sessione scaduta, effettua di nuovo il login')
  }
  if (router.currentRoute.value.meta.requiresAuth) {
    router.push({ name: 'login' })
  }
})

async function bootstrap() {
  if (!reduceMotion) {
    // Import dinamico: la libreria non viene caricata se non serve.
    const { MotionPlugin } = await import('@vueuse/motion')
    app.use(MotionPlugin)
  }
  // Ripristina la sessione al refresh PRIMA di montare, così le guard
  // vedono già lo stato corretto.
  await auth.fetchMe().catch(() => {})
  app.use(router)
  app.mount('#app')
}

bootstrap()
