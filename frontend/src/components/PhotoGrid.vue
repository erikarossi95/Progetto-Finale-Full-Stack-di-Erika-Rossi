<script setup>
import { ref } from 'vue'
import { vAutoAnimate } from '@formkit/auto-animate/vue'

const props = defineProps({
  photos: { type: Array, default: () => [] },
  // Base URL per costruire l'URL assoluto dei media (origin del backend).
  mediaBase: { type: String, default: '' },
  // Mostra il pulsante di eliminazione (solo organizzatore).
  canDelete: { type: Boolean, default: false },
})
const emit = defineEmits(['delete'])

const lightbox = ref(null)

function url(photo) {
  return props.mediaBase + photo.file_url
}
</script>

<template>
  <div>
    <!-- Empty state -->
    <div
      v-if="!photos.length"
      class="rounded-2xl border-2 border-dashed border-surface-border bg-surface/50 py-16 text-center"
    >
      <p class="text-4xl">🖼️</p>
      <p class="mt-3 font-semibold text-ink-soft">Ancora nessun contenuto</p>
      <p class="text-sm text-ink-muted">Le foto e i video caricati appariranno qui.</p>
    </div>

    <!-- Griglia (auto-animate: pop-in delle nuove foto, rimozione fluida) -->
    <div v-else v-auto-animate class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
      <div
        v-for="photo in photos"
        :key="photo.id"
        class="group relative aspect-square overflow-hidden rounded-xl bg-ink/5 shadow-card transition-shadow duration-300 hover:shadow-card-hover"
      >
        <!-- Media -->
        <img
          v-if="photo.file_type === 'image'"
          :src="url(photo)"
          :alt="photo.uploader_name ? `Foto di ${photo.uploader_name}` : 'Foto dell\'evento'"
          loading="lazy"
          decoding="async"
          class="h-full w-full cursor-zoom-in object-cover transition-transform duration-500 ease-snappy group-hover:scale-110"
          @click="lightbox = photo"
        />
        <video
          v-else
          :src="url(photo)"
          controls
          preload="metadata"
          class="h-full w-full bg-black object-cover"
        />

        <!-- Etichetta autore -->
        <div
          v-if="photo.uploader_name"
          class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink/70 to-transparent px-2 pb-2 pt-6"
        >
          <span class="text-xs font-medium text-white">{{ photo.uploader_name }}</span>
        </div>

        <!-- Elimina (moderazione) -->
        <button
          v-if="canDelete"
          class="absolute right-2 top-2 grid h-9 w-9 place-items-center rounded-full bg-white/90 text-error shadow backdrop-blur transition hover:bg-error hover:text-white focus-visible:opacity-100 opacity-100 [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100"
          :aria-label="`Elimina ${photo.file_type === 'video' ? 'video' : 'foto'}`"
          title="Elimina"
          @click.stop="emit('delete', photo)"
        >
          🗑
        </button>
      </div>
    </div>

    <!-- Lightbox immagini -->
    <transition name="lb">
      <div
        v-if="lightbox"
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/85 p-4 backdrop-blur-sm"
        v-focus-trap="() => (lightbox = null)"
        role="dialog"
        aria-modal="true"
        aria-label="Foto a schermo intero"
        @click="lightbox = null"
      >
        <img
          :src="url(lightbox)"
          :alt="lightbox.uploader_name ? `Foto di ${lightbox.uploader_name}` : 'Foto dell\'evento'"
          class="max-h-[90vh] max-w-full rounded-2xl shadow-card-hover"
        />
        <button
          class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/10 text-2xl text-white/90 backdrop-blur hover:bg-white/20"
          aria-label="Chiudi"
        >
          ×
        </button>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.lb-enter-active,
.lb-leave-active {
  transition: opacity 0.22s ease;
}
.lb-enter-from,
.lb-leave-to {
  opacity: 0;
}
.lb-enter-active img,
.lb-leave-active img {
  transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
.lb-enter-from img {
  transform: scale(0.94);
}
</style>
