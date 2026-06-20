<script setup>
import { reactive, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { parseApiError } from '@/api/axios'
import { fadeUp, stagger } from '@/composables/motion'

const auth = useAuthStore()
const ui = useUiStore()

// Dati anagrafici.
const info = reactive({ name: auth.user?.name || '', email: auth.user?.email || '' })
const infoErrors = reactive({ name: '', email: '', current_password: '' })
const infoCurrentPassword = ref('')
const savingInfo = ref(false)

// Cambio password.
const pwd = reactive({ current_password: '', new_password: '', confirm: '' })
const pwdErrors = reactive({ current_password: '', new_password: '', confirm: '' })
const savingPwd = ref(false)

const emailChanged = () =>
  info.email.trim().toLowerCase() !== (auth.user?.email || '').toLowerCase()

async function saveInfo() {
  infoErrors.name = info.name.trim().length < 2 ? 'Il nome deve avere almeno 2 caratteri' : ''
  infoErrors.email = !/^\S+@\S+\.\S+$/.test(info.email) ? 'Email non valida' : ''
  // Per cambiare email serve la password attuale.
  infoErrors.current_password =
    emailChanged() && !infoCurrentPassword.value
      ? 'Inserisci la password attuale per cambiare email'
      : ''
  if (infoErrors.name || infoErrors.email || infoErrors.current_password) return

  savingInfo.value = true
  try {
    const payload = { name: info.name.trim(), email: info.email.trim() }
    if (emailChanged()) payload.current_password = infoCurrentPassword.value
    await auth.updateProfile(payload)
    infoCurrentPassword.value = ''
    ui.success('Profilo aggiornato')
  } catch (e) {
    const { message, fields } = parseApiError(e, 'Aggiornamento fallito')
    Object.assign(infoErrors, fields)
    ui.error(message)
  } finally {
    savingInfo.value = false
  }
}

async function savePassword() {
  pwdErrors.current_password = !pwd.current_password ? 'Inserisci la password attuale' : ''
  pwdErrors.new_password = pwd.new_password.length < 8 ? 'Almeno 8 caratteri' : ''
  pwdErrors.confirm = pwd.confirm !== pwd.new_password ? 'Le password non coincidono' : ''
  if (pwdErrors.current_password || pwdErrors.new_password || pwdErrors.confirm) return

  savingPwd.value = true
  try {
    await auth.updateProfile({
      current_password: pwd.current_password,
      new_password: pwd.new_password,
    })
    pwd.current_password = pwd.new_password = pwd.confirm = ''
    ui.success('Password aggiornata')
  } catch (e) {
    const { message, fields } = parseApiError(e, 'Cambio password fallito')
    Object.assign(pwdErrors, fields)
    ui.error(message)
  } finally {
    savingPwd.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl px-4 py-10">
    <div v-motion="fadeUp">
      <h1 class="text-3xl font-bold sm:text-4xl">Il tuo profilo</h1>
      <p class="mt-2 text-ink-soft">Aggiorna i tuoi dati e la password di accesso.</p>
    </div>

    <!-- Dati anagrafici -->
    <section v-motion="stagger(0)" class="card mt-8 p-6 sm:p-8">
      <h2 class="text-lg font-bold">Dati account</h2>
      <form class="mt-5 space-y-5" novalidate @submit.prevent="saveInfo">
        <div>
          <label class="label" for="p-name">Nome</label>
          <input
            id="p-name"
            v-model.trim="info.name"
            type="text"
            class="input"
            :class="infoErrors.name && 'input-error'"
          />
          <p v-if="infoErrors.name" class="field-error" role="alert">{{ infoErrors.name }}</p>
        </div>
        <div>
          <label class="label" for="p-email">Email</label>
          <input
            id="p-email"
            v-model.trim="info.email"
            type="email"
            class="input"
            :class="infoErrors.email && 'input-error'"
          />
          <p v-if="infoErrors.email" class="field-error" role="alert">{{ infoErrors.email }}</p>
        </div>
        <div v-if="emailChanged()">
          <label class="label" for="p-cur"
            >Password attuale
            <span class="font-normal text-ink-muted">(richiesta per cambiare email)</span></label
          >
          <input
            id="p-cur"
            v-model="infoCurrentPassword"
            type="password"
            class="input"
            :class="infoErrors.current_password && 'input-error'"
          />
          <p v-if="infoErrors.current_password" class="field-error" role="alert">
            {{ infoErrors.current_password }}
          </p>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="btn-primary" :disabled="savingInfo">
            {{ savingInfo ? 'Salvataggio…' : 'Salva dati' }}
          </button>
        </div>
      </form>
    </section>

    <!-- Cambio password -->
    <section v-motion="stagger(1)" class="card mt-6 p-6 sm:p-8">
      <h2 class="text-lg font-bold">Cambia password</h2>
      <form class="mt-5 space-y-5" novalidate @submit.prevent="savePassword">
        <div>
          <label class="label" for="pw-cur">Password attuale</label>
          <input
            id="pw-cur"
            v-model="pwd.current_password"
            type="password"
            autocomplete="current-password"
            class="input"
            :class="pwdErrors.current_password && 'input-error'"
          />
          <p v-if="pwdErrors.current_password" class="field-error" role="alert">
            {{ pwdErrors.current_password }}
          </p>
        </div>
        <div>
          <label class="label" for="pw-new">Nuova password</label>
          <input
            id="pw-new"
            v-model="pwd.new_password"
            type="password"
            autocomplete="new-password"
            class="input"
            :class="pwdErrors.new_password && 'input-error'"
            placeholder="Almeno 8 caratteri"
          />
          <p v-if="pwdErrors.new_password" class="field-error" role="alert">
            {{ pwdErrors.new_password }}
          </p>
        </div>
        <div>
          <label class="label" for="pw-conf">Conferma nuova password</label>
          <input
            id="pw-conf"
            v-model="pwd.confirm"
            type="password"
            autocomplete="new-password"
            class="input"
            :class="pwdErrors.confirm && 'input-error'"
          />
          <p v-if="pwdErrors.confirm" class="field-error" role="alert">{{ pwdErrors.confirm }}</p>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="btn-primary" :disabled="savingPwd">
            {{ savingPwd ? 'Aggiornamento…' : 'Aggiorna password' }}
          </button>
        </div>
      </form>
    </section>
  </div>
</template>
