<script setup>
import { computed, watch, onMounted, onBeforeUnmount, ref } from 'vue'
import { fullDateTime, initialOf, avatarColor } from '@/utils/format'
import { useLikesStore } from '@/stores/likes'

const likes = useLikesStore()

const props = defineProps({
  photos: { type: Array, default: () => [] },
  mediaBase: { type: String, default: '' },
  // Indice del media aperto (v-model). -1 = chiuso.
  index: { type: Number, default: -1 },
  // Copertina evento: usata come sfondo sfocato d'atmosfera.
  cover: { type: String, default: null },
})
const emit = defineEmits(['update:index'])

const open = computed(() => props.index >= 0 && props.index < props.photos.length)
const current = computed(() => props.photos[props.index] || null)
const hasPrev = computed(() => props.index > 0)
const hasNext = computed(() => props.index < props.photos.length - 1)

function url(p) {
  return props.mediaBase + p.file_url
}
function close() {
  emit('update:index', -1)
}
function prev() {
  if (hasPrev.value) emit('update:index', props.index - 1)
}
function next() {
  if (hasNext.value) emit('update:index', props.index + 1)
}

// Tastiera: frecce per navigare (Esc è gestito da v-focus-trap).
function onKey(e) {
  if (!open.value) return
  if (e.key === 'ArrowLeft') prev()
  else if (e.key === 'ArrowRight') next()
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey)
  document.body.style.overflow = ''
})
// Blocca lo scroll del body quando aperto.
watch(open, (v) => {
  document.body.style.overflow = v ? 'hidden' : ''
})

// Swipe su mobile per navigare.
let startX = 0
let startY = 0
function onTouchStart(e) {
  const t = e.changedTouches[0]
  startX = t.clientX
  startY = t.clientY
}
function onTouchEnd(e) {
  const t = e.changedTouches[0]
  const dx = t.clientX - startX
  const dy = t.clientY - startY
  if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
    dx > 0 ? prev() : next()
  }
}

// --- Doppio tap → cuore (DECORATIVO: animazione locale, nessun like persistito) ---
const hearts = ref([])
let lastTap = 0
function onMediaTap() {
  const now = Date.now()
  if (now - lastTap < 300) {
    spawnHeart()
    if (current.value) likes.likeOnce(current.value) // doppio-tap = like reale
    lastTap = 0
  } else {
    lastTap = now
  }
}
function spawnHeart() {
  const id = Math.random()
  hearts.value.push(id)
  setTimeout(() => (hearts.value = hearts.value.filter((h) => h !== id)), 900)
}
</script>

<template>
  <teleport to="body">
    <transition name="viewer">
      <div
        v-if="open && current"
        class="fixed inset-0 z-[70] flex flex-col bg-ink"
        v-focus-trap="close"
        role="dialog"
        aria-modal="true"
        aria-label="Visualizzatore foto e video"
      >
        <!-- Sfondo: copertina evento sfocata (ambiance), altrimenti tinta unita -->
        <div class="absolute inset-0 overflow-hidden">
          <img
            v-if="cover"
            :src="cover"
            alt=""
            class="h-full w-full scale-110 object-cover opacity-30 blur-2xl"
          />
          <div class="absolute inset-0 bg-ink/80 backdrop-blur-sm" />
        </div>

        <!-- Top bar: autore + contatore + chiudi -->
        <div class="relative z-10 flex items-center justify-between gap-3 px-4 py-3 text-white">
          <div class="flex min-w-0 items-center gap-3">
            <span
              class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-bold text-white"
              :style="{ backgroundColor: avatarColor(current.uploader_name) }"
            >
              {{ initialOf(current.uploader_name) }}
            </span>
            <div class="min-w-0">
              <p class="truncate font-display text-sm font-bold">
                {{ current.uploader_name || 'Ospite anonimo' }}
              </p>
              <p class="truncate text-xs text-white/60">{{ fullDateTime(current.created_at) }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2 sm:gap-3">
            <!-- Like + conteggio -->
            <button
              class="flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-white/20 active:scale-90"
              :aria-pressed="likes.isLiked(current.id)"
              :aria-label="likes.isLiked(current.id) ? 'Togli il cuore' : 'Metti un cuore'"
              @click="likes.toggle(current)"
            >
              <svg
                viewBox="0 0 24 24"
                class="h-5 w-5"
                :class="likes.isLiked(current.id) ? 'text-accent-400' : 'text-white'"
                :fill="likes.isLiked(current.id) ? 'currentColor' : 'none'"
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
              {{ current.likes || 0 }}
            </button>
            <span class="text-sm text-white/70">{{ index + 1 }} / {{ photos.length }}</span>
            <button
              class="grid h-11 w-11 place-items-center rounded-full bg-white/10 text-xl text-white transition hover:bg-white/20"
              aria-label="Chiudi"
              @click="close"
            >
              ×
            </button>
          </div>
        </div>

        <!-- Media -->
        <div
          class="relative z-10 flex flex-1 select-none items-center justify-center overflow-hidden px-2 pb-4 sm:px-4"
          @click.self="close"
          @touchstart.passive="onTouchStart"
          @touchend.passive="onTouchEnd"
        >
          <div class="relative" @click="onMediaTap">
            <img
              v-if="current.file_type === 'image'"
              :src="url(current)"
              :alt="current.uploader_name ? `Foto di ${current.uploader_name}` : 'Foto'"
              class="max-h-[80vh] max-w-full rounded-xl object-contain"
            />
            <video
              v-else
              :src="url(current)"
              controls
              autoplay
              playsinline
              class="max-h-[80vh] max-w-full rounded-xl bg-black"
            />

            <!-- Cuori decorativi -->
            <div class="pointer-events-none absolute inset-0 grid place-items-center">
              <span v-for="h in hearts" :key="h" class="heart-pop text-7xl">❤️</span>
            </div>
          </div>

          <!-- Frecce (desktop) -->
          <button
            v-if="hasPrev"
            class="absolute left-2 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-2xl text-white backdrop-blur transition hover:bg-white/25 sm:left-4"
            aria-label="Precedente"
            @click.stop="prev"
          >
            ‹
          </button>
          <button
            v-if="hasNext"
            class="absolute right-2 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-2xl text-white backdrop-blur transition hover:bg-white/25 sm:right-4"
            aria-label="Successivo"
            @click.stop="next"
          >
            ›
          </button>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.viewer-enter-active,
.viewer-leave-active {
  transition: opacity 0.22s ease;
}
.viewer-enter-from,
.viewer-leave-to {
  opacity: 0;
}
.viewer-enter-active img,
.viewer-enter-active video {
  transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
.viewer-enter-from img,
.viewer-enter-from video {
  transform: scale(0.96);
}
.heart-pop {
  position: absolute;
  animation: heart-pop 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  filter: drop-shadow(0 6px 16px rgba(0, 0, 0, 0.4));
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
    transform: scale(1.1) translateY(-30px);
    opacity: 0;
  }
}
@media (prefers-reduced-motion: reduce) {
  .heart-pop {
    animation-duration: 0.001ms;
  }
}
</style>
