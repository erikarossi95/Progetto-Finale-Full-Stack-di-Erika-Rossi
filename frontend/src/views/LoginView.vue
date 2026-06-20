<script setup>
import { reactive, ref } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { parseApiError } from '@/api/axios'
import AppLogo from '@/components/AppLogo.vue'
import { fadeUp } from '@/composables/motion'

const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()
const route = useRoute()

const form = reactive({ email: '', password: '' })
const errors = reactive({ email: '', password: '' })
const submitting = ref(false)

// Validazione client per UX immediata (quella autorevole è lato server).
function validate() {
  errors.email = !form.email ? 'Inserisci la tua email' : ''
  errors.password = !form.password ? 'Inserisci la password' : ''
  return !errors.email && !errors.password
}

async function onSubmit() {
  if (!validate()) return
  submitting.value = true
  try {
    const user = await auth.login({ email: form.email, password: form.password })
    ui.success(`Bentornato, ${user.name}!`)
    router.push(route.query.redirect || { name: 'dashboard' })
  } catch (e) {
    const { message, fields } = parseApiError(e, 'Login fallito')
    Object.assign(errors, fields)
    ui.error(message)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="grid min-h-[calc(100vh-4rem)] place-items-center px-4 py-12">
    <div v-motion="fadeUp" class="w-full max-w-md">
      <div class="mb-6 flex justify-center">
        <RouterLink to="/"><AppLogo /></RouterLink>
      </div>

      <div class="card p-8">
        <h1 class="text-2xl font-bold">Bentornato 👋</h1>
        <p class="mt-1 text-sm text-ink-soft">Accedi per gestire i tuoi eventi e le gallerie.</p>

        <form class="mt-8 space-y-5" novalidate @submit.prevent="onSubmit">
          <div>
            <label class="label" for="email">Email</label>
            <input
              id="email"
              v-model.trim="form.email"
              type="email"
              autocomplete="email"
              class="input"
              :class="errors.email && 'input-error'"
              placeholder="tu@example.com"
            />
            <transition name="err">
              <p v-if="errors.email" class="field-error" role="alert">
                <span aria-hidden="true">⚠</span> {{ errors.email }}
              </p>
            </transition>
          </div>

          <div>
            <label class="label" for="password">Password</label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              class="input"
              :class="errors.password && 'input-error'"
              placeholder="••••••••"
            />
            <transition name="err">
              <p v-if="errors.password" class="field-error" role="alert">
                <span aria-hidden="true">⚠</span> {{ errors.password }}
              </p>
            </transition>
          </div>

          <button type="submit" class="btn-primary w-full py-3" :disabled="submitting">
            <span
              v-if="submitting"
              class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
            />
            {{ submitting ? 'Accesso in corso…' : 'Accedi' }}
          </button>
        </form>
      </div>

      <p class="mt-6 text-center text-sm text-ink-soft">
        Non hai un account?
        <RouterLink to="/register" class="font-semibold text-brand-600 hover:text-brand-700"
          >Registrati gratis</RouterLink
        >
      </p>
    </div>
  </div>
</template>
