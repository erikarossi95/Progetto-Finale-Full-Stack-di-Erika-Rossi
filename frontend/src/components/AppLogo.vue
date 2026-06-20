<script setup>
defineProps({
  // Variante chiara per sfondi scuri/gradiente.
  light: { type: Boolean, default: false },
})
</script>

<template>
  <span class="inline-flex items-center gap-2 font-display font-extrabold text-xl tracking-tight">
    <span
      class="grid h-8 w-8 place-items-center rounded-xl text-white shadow-card"
      :class="light ? 'bg-white/20' : 'bg-brand-gradient'"
    >
      📸
    </span>
    <span :class="light ? 'text-white' : 'text-ink'"
      >Snap<span class="text-brand-500" :class="light && 'text-white'">ly</span></span
    >
  </span>
</template>
