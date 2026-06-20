<script setup>
import { ref, computed } from 'vue'
import { vAutoAnimate } from '@formkit/auto-animate/vue'
import MediaLightbox from '@/components/MediaLightbox.vue'
import { timeAgo, initialOf, avatarColor } from '@/utils/format'
import { useLikesStore } from '@/stores/likes'

const likes = useLikesStore()

const props = defineProps({
  photos: { type: Array, default: () => [] },
  mediaBase: { type: String, default: '' },
  view: { type: String, default: 'grid' }, // 'grid' | 'feed'
  cover: { type: String, default: null }, // copertina evento, usata come sfondo del lightbox
  // Paginazione lato server: il genitore carica le pagine successive.
  hasMore: { type: Boolean, default: false },
  loadingMore: { type: Boolean, default: false },
})
const emit = defineEmits(['load-more'])

// Le foto mostrate sono quelle già caricate dal genitore (paginazione server).
const visible = computed(() => props.photos)

const lightboxIndex = ref(-1)
function openAt(i) {
  lightboxIndex.value = i
}

function url(p) {
  return props.mediaBase + p.file_url
}
// Miniatura per la griglia (fallback all'originale se assente).
function thumb(p) {
  return props.mediaBase + (p.thumb_url || p.file_url)
}

// --- Doppio tap → cuore nel feed (DECORATIVO, nessun like persistito) ---
// Singolo tap = apre il lightbox; doppio tap = cuore.
const heartFor = ref({}) // photoId -> chiave animazione (per ri-triggerare)
let tapTimer = null
function onFeedTap(absIndex, photo) {
  if (tapTimer) {
    clearTimeout(tapTimer)
    tapTimer = null
    heartFor.value = { ...heartFor.value, [photo.id]: Date.now() } // animazione
    likes.likeOnce(photo) // doppio-tap = like reale (mai unlike)
  } else {
    tapTimer = setTimeout(() => {
      tapTimer = null
      openAt(absIndex)
    }, 240)
  }
}
</script>

<template>
  <div>
    <!-- ===== GRIGLIA (stile profilo IG) ===== -->
    <div v-if="view === 'grid'" v-auto-animate class="grid grid-cols-3 gap-1 sm:gap-2">
      <button
        v-for="(p, i) in visible"
        :key="p.id"
        class="group relative aspect-square overflow-hidden rounded-md bg-ink/5 transition focus-visible:shadow-focus sm:rounded-lg"
        :aria-label="`Apri ${p.file_type === 'video' ? 'video' : 'foto'}${p.uploader_name ? ' di ' + p.uploader_name : ''}`"
        @click="openAt(i)"
      >
        <img
          v-if="p.file_type === 'image'"
          :src="thumb(p)"
          :alt="p.uploader_name ? `Foto di ${p.uploader_name}` : 'Foto dell\'evento'"
          loading="lazy"
          decoding="async"
          class="h-full w-full object-cover transition-transform duration-500 ease-snappy group-hover:scale-105"
        />
        <template v-else>
          <video :src="url(p)" preload="metadata" muted class="h-full w-full object-cover" />
          <span
            class="absolute right-1.5 top-1.5 grid h-6 w-6 place-items-center rounded-full bg-ink/55 text-xs text-white backdrop-blur"
            >▶</span
          >
        </template>
        <div
          class="pointer-events-none absolute inset-0 flex items-end justify-between gap-1 bg-gradient-to-t from-ink/60 to-transparent p-2 opacity-0 transition group-hover:opacity-100"
        >
          <span v-if="p.uploader_name" class="truncate text-xs font-semibold text-white">{{
            p.uploader_name
          }}</span>
          <span
            v-if="p.likes"
            class="ml-auto flex shrink-0 items-center gap-1 text-xs font-semibold text-white"
          >
            <svg viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
              <path d="M12 21 4.2 13.4a5.5 5.5 0 0 1 7.8-7.8l.0 .0a5.5 5.5 0 0 1 7.8 7.8L12 21Z" />
            </svg>
            {{ p.likes }}
          </span>
        </div>
      </button>
    </div>

    <!-- ===== FEED (stile bacheca) ===== -->
    <div v-else v-auto-animate class="mx-auto flex max-w-xl flex-col gap-6">
      <article v-for="(p, i) in visible" :key="p.id" class="card overflow-hidden">
        <!-- Header post -->
        <header class="flex items-center gap-3 px-4 py-3">
          <span
            class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-bold text-white"
            :style="{ backgroundColor: avatarColor(p.uploader_name) }"
          >
            {{ initialOf(p.uploader_name) }}
          </span>
          <div class="min-w-0">
            <p class="truncate font-display text-sm font-bold text-ink">
              {{ p.uploader_name || 'Ospite anonimo' }}
            </p>
            <p class="truncate text-xs text-ink-muted">{{ timeAgo(p.created_at) }}</p>
          </div>
        </header>

        <!-- Media (doppio tap = cuore decorativo, singolo tap = lightbox) -->
        <div class="relative cursor-pointer select-none bg-ink/5" @click="onFeedTap(i, p)">
          <img
            v-if="p.file_type === 'image'"
            :src="url(p)"
            :alt="p.uploader_name ? `Foto di ${p.uploader_name}` : 'Foto dell\'evento'"
            loading="lazy"
            decoding="async"
            class="max-h-[34rem] w-full object-cover"
          />
          <template v-else>
            <video
              :src="url(p)"
              controls
              playsinline
              preload="metadata"
              class="max-h-[34rem] w-full bg-black object-contain"
              @click.stop
            />
          </template>
          <!-- Cuore decorativo -->
          <div class="pointer-events-none absolute inset-0 grid place-items-center">
            <span v-if="heartFor[p.id]" :key="heartFor[p.id]" class="heart-pop text-7xl">❤️</span>
          </div>
        </div>

        <!-- Footer: like reale + conteggio -->
        <footer class="flex items-center gap-3 px-4 py-3">
          <button
            class="flex items-center gap-2 transition active:scale-90"
            :aria-pressed="likes.isLiked(p.id)"
            :aria-label="likes.isLiked(p.id) ? 'Togli il cuore' : 'Metti un cuore'"
            @click="likes.toggle(p)"
          >
            <svg
              viewBox="0 0 24 24"
              class="h-6 w-6 transition"
              :class="likes.isLiked(p.id) ? 'text-accent-500' : 'text-ink-muted'"
              :fill="likes.isLiked(p.id) ? 'currentColor' : 'none'"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path
                d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"
              />
            </svg>
            <span
              class="text-sm font-semibold"
              :class="likes.isLiked(p.id) ? 'text-accent-600' : 'text-ink-soft'"
              >{{ p.likes || 0 }}</span
            >
          </button>
          <span class="text-xs text-ink-muted">Doppio tap sulla foto per il cuore</span>
        </footer>
      </article>
    </div>

    <!-- Carica altri (paginazione lato server) -->
    <div v-if="hasMore" class="mt-8 text-center">
      <button class="btn-secondary" :disabled="loadingMore" @click="emit('load-more')">
        <span
          v-if="loadingMore"
          class="h-4 w-4 animate-spin rounded-full border-2 border-brand-300 border-t-brand-600"
        />
        {{ loadingMore ? 'Caricamento…' : 'Carica altri' }}
      </button>
    </div>

    <!-- Lightbox condiviso -->
    <MediaLightbox
      :photos="photos"
      :media-base="mediaBase"
      :cover="cover"
      v-model:index="lightboxIndex"
    />
  </div>
</template>

<style scoped>
.heart-pop {
  animation: heart-pop 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  filter: drop-shadow(0 6px 16px rgba(31, 34, 51, 0.35));
}
@keyframes heart-pop {
  0% {
    transform: scale(0.2);
    opacity: 0;
  }
  15% {
    transform: scale(1.2);
    opacity: 1;
  }
  35% {
    transform: scale(1);
    opacity: 1;
  }
  100% {
    transform: scale(1.1) translateY(-26px);
    opacity: 0;
  }
}
@media (prefers-reduced-motion: reduce) {
  .heart-pop {
    animation-duration: 0.001ms;
  }
}
</style>
