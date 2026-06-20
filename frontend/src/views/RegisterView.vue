<script setup>
import { reactive, ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { parseApiError } from '@/api/axios'
import AppLogo from '@/components/AppLogo.vue'
import { fadeUp } from '@/composables/motion'

const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()

const form = reactive({ name: '', email: '', password: '' })
const errors = reactive({ name: '', email: '', password: '' })
const submitting = ref(false)

function validate() {
  errors.name = form.name.trim().length < 2 ? 'Il nome deve avere almeno 2 caratteri' : ''
  errors.email = !/^\S+@\S+\.\S+$/.test(form.email) ? 'Inserisci un’email valida' : ''
  errors.password = form.password.length < 8 ? 'La password deve avere almeno 8 caratteri' : ''
  return !errors.name && !errors.email && !errors.password
}

async function onSubmit() {
  if (!validate()) return
  submitting.value = true
  try {
    const user = await auth.register({ ...form })
    ui.success(`Benvenuta su Snaply, ${user.name}!`)
    router.push({ name: 'dashboard' })
  } catch (e) {
    const { message, fields } = parseApiError(e, 'Registrazione fallita')
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
        <h1 class="text-2xl font-bold">Crea il tuo account</h1>
        <p class="mt-1 text-sm text-ink-soft">Gratis. Nessuna carta richiesta.</p>

        <form class="mt-8 space-y-5" novalidate @submit.prevent="onSubmit">
          <div>
            <label class="label" for="name">Nome</label>
            <input
              id="name"
              v-model.trim="form.name"
              type="text"
              autocomplete="name"
              class="input"
              :class="errors.name && 'input-error'"
              placeholder="Il tuo nome"
            />
            <transition name="err">
              <p v-if="errors.name" class="field-error" role="alert">
                <span aria-hidden="true">⚠</span> {{ errors.name }}
              </p>
            </transition>
          </div>

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
              autocomplete="new-password"
              class="input"
              :class="errors.password && 'input-error'"
              placeholder="Almeno 8 caratteri"
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
            {{ submitting ? 'Creazione account…' : 'Registrati' }}
          </button>
        </form>
      </div>

      <p class="mt-6 text-center text-sm text-ink-soft">
        Hai già un account?
        <RouterLink to="/login" class="font-semibold text-brand-600 hover:text-brand-700"
          >Accedi</RouterLink
        >
      </p>
    </div>
  </div>
</template>
