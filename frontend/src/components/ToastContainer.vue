<script setup>
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()

// Stili per tipo di toast, coerenti con i colori di stato del brand.
const styles = {
  success: 'bg-success-light text-success-dark border-success/30',
  error: 'bg-error-light text-error-dark border-error/30',
  info: 'bg-info-light text-info-dark border-info/30',
}
const icons = { success: '✓', error: '!', info: 'i' }
</script>

<template>
  <div
    class="pointer-events-none fixed inset-x-0 top-0 z-[60] flex flex-col items-center gap-2 p-4 sm:inset-x-auto sm:right-0 sm:items-end"
  >
    <transition-group name="toast">
      <div
        v-for="t in ui.toasts"
        :key="t.id"
        class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border px-4 py-3 shadow-card-hover"
        :class="styles[t.type] || styles.info"
        role="status"
        aria-live="polite"
      >
        <span
          class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-white/60 text-xs font-bold"
          aria-hidden="true"
        >
          {{ icons[t.type] || icons.info }}
        </span>
        <p class="flex-1 text-sm font-medium leading-snug">{{ t.message }}</p>
        <button
          class="shrink-0 text-lg leading-none opacity-60 transition hover:opacity-100"
          aria-label="Chiudi notifica"
          @click="ui.dismiss(t.id)"
        >
          ×
        </button>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
/* Slide-in da destra (desktop) / dall'alto (mobile), fade-out morbido. */
.toast-enter-active {
  transition: all 0.32s cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-leave-active {
  transition: all 0.25s ease;
  position: absolute;
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(24px) scale(0.96);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(24px) scale(0.96);
}
.toast-move {
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@media (max-width: 639px) {
  .toast-enter-from,
  .toast-leave-to {
    transform: translateY(-16px) scale(0.96);
  }
}
</style>
