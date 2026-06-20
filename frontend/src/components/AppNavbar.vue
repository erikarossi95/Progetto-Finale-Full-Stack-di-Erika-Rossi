<script setup>
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import AppLogo from '@/components/AppLogo.vue'

const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  ui.success('Logout effettuato')
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="sticky top-0 z-40 border-b border-surface-border bg-surface/80 backdrop-blur">
    <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
      <RouterLink :to="auth.isAuthenticated ? '/dashboard' : '/'" class="shrink-0">
        <AppLogo />
      </RouterLink>

      <!-- Utente autenticato -->
      <div v-if="auth.isAuthenticated" class="flex items-center gap-1 sm:gap-2">
        <span class="mr-1 hidden text-sm text-ink-soft md:inline">
          Ciao <span class="font-semibold text-ink">{{ auth.user?.name }}</span>
        </span>
        <RouterLink
          to="/dashboard"
          class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-soft transition hover:bg-brand-50 hover:text-brand-700"
          active-class="bg-brand-50 text-brand-700"
        >
          Eventi
        </RouterLink>
        <RouterLink
          to="/profile"
          class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-soft transition hover:bg-brand-50 hover:text-brand-700"
          active-class="bg-brand-50 text-brand-700"
        >
          Profilo
        </RouterLink>
        <button class="btn-secondary ml-1" @click="handleLogout">Logout</button>
      </div>

      <!-- Visitatore -->
      <div v-else class="flex items-center gap-2">
        <RouterLink to="/login" class="btn-ghost">Accedi</RouterLink>
        <RouterLink to="/register" class="btn-primary">Inizia gratis</RouterLink>
      </div>
    </nav>
  </header>
</template>
