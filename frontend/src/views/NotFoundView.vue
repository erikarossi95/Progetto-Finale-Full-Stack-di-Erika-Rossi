<script setup>
import { RouterLink } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import { fadeUp } from '@/composables/motion'
</script>

<template>
  <div class="grid min-h-screen place-items-center px-4 text-center">
    <div v-motion="fadeUp">
      <RouterLink to="/" class="inline-flex"><AppLogo /></RouterLink>
      <p class="mt-8 text-7xl font-extrabold text-brand-500">404</p>
      <h1 class="mt-2 text-2xl font-bold">Pagina non trovata</h1>
      <p class="mt-2 text-ink-soft">La pagina che cerchi non esiste o è stata spostata.</p>
      <RouterLink to="/" class="btn-primary mt-6">Torna alla home</RouterLink>
    </div>
  </div>
</template>
