<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useEventsStore } from '@/stores/events'
import { useUiStore } from '@/stores/ui'
import { parseApiError } from '@/api/axios'
import { mediaBaseUrl, publicEventUrl, coverUrl } from '@/utils/url'
import PhotoGrid from '@/components/PhotoGrid.vue'
import QrShare from '@/components/QrShare.vue'
import EventFormModal from '@/components/EventFormModal.vue'

const route = useRoute()
const router = useRouter()
const events = useEventsStore()
const ui = useUiStore()

const id = Number(route.params.id)
const editOpen = ref(false)
const editRef = ref(null)
const confirmDelete = ref(false)

// Copertina
const coverInput = ref(null)
const uploadingCover = ref(false)
const COVER_MIME = ['image/jpeg', 'image/png', 'image/webp']

// Filtro galleria
const filter = ref('all') // all | image | video

// Paginazione server
const photoPage = ref(1)
const loadingMore = ref(false)

const event = computed(() => events.currentEvent)
const mediaBase = mediaBaseUrl()
const shareUrl = computed(() => (event.value ? publicEventUrl(event.value.slug) : ''))
const cover = computed(() => coverUrl(event.value?.cover_image_url))

const allPhotos = computed(() => event.value?.photos || [])
const imageCount = computed(() => allPhotos.value.filter((p) => p.file_type === 'image').length)
const videoCount = computed(() => allPhotos.value.filter((p) => p.file_type === 'video').length)
const filteredPhotos = computed(() => {
  if (filter.value === 'all') return allPhotos.value
  return allPhotos.value.filter((p) => p.file_type === filter.value)
})
const hasMorePhotos = computed(() => allPhotos.value.length < (event.value?.photo_count || 0))

async function loadMorePhotos() {
  if (loadingMore.value || !hasMorePhotos.value) return
  loadingMore.value = true
  try {
    await events.fetchMorePhotos(id, photoPage.value + 1)
    photoPage.value += 1
  } catch (e) {
    ui.error(parseApiError(e, 'Impossibile caricare altri media').message)
  } finally {
    loadingMore.value = false
  }
}

const formattedDate = computed(() => {
  if (!event.value?.event_date) return null
  return new Date(event.value.event_date).toLocaleDateString('it-IT', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
})

onMounted(load)

async function load() {
  try {
    await events.fetchEvent(id)
  } catch (e) {
    const { code } = parseApiError(e)
    if (code === 'NOT_FOUND' || code === 'FORBIDDEN') {
      ui.error('Evento non disponibile')
      router.push({ name: 'dashboard' })
      return
    }
    ui.error('Impossibile caricare l’evento')
  }
}

async function handleEdit(payload, media = {}) {
  try {
    await events.updateEvent(id, payload) // include avatar_emoji
    // Copertina
    if (media.cover?.file) await events.uploadCover(id, media.cover.file)
    else if (media.cover?.remove) await events.deleteCover(id)
    // Avatar immagine
    if (media.avatar?.file) await events.uploadAvatar(id, media.avatar.file)
    else if (media.avatar?.remove) await events.deleteAvatar(id)
    ui.success('Evento aggiornato')
    editOpen.value = false
  } catch (e) {
    const { message, fields } = parseApiError(e, 'Aggiornamento fallito')
    editRef.value?.setFieldErrors(fields)
    editRef.value?.stopSubmitting()
    ui.error(message)
  }
}

// --- Copertina dall'header ---
function pickCover() {
  coverInput.value?.click()
}
async function onCoverChange(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return
  if (!COVER_MIME.includes(file.type)) return ui.error('Usa un’immagine JPG, PNG o WebP')
  if (file.size > 25 * 1024 * 1024) return ui.error('Immagine troppo grande (max 25 MB)')
  uploadingCover.value = true
  try {
    await events.uploadCover(id, file)
    ui.success('Copertina aggiornata')
  } catch (err) {
    ui.error(parseApiError(err, 'Caricamento copertina fallito').message)
  } finally {
    uploadingCover.value = false
  }
}
async function removeCover() {
  try {
    await events.deleteCover(id)
    ui.success('Copertina rimossa')
  } catch (e) {
    ui.error(parseApiError(e, 'Rimozione fallita').message)
  }
}

async function handleDeleteEvent() {
  try {
    await events.deleteEvent(id)
    ui.success('Evento eliminato')
    router.push({ name: 'dashboard' })
  } catch (e) {
    ui.error(parseApiError(e, 'Eliminazione fallita').message)
  }
}

async function handleDeletePhoto(photo) {
  try {
    await events.deletePhoto(photo.id)
    ui.success('Contenuto eliminato')
  } catch (e) {
    ui.error(parseApiError(e, 'Eliminazione fallita').message)
  }
}
</script>

<template>
  <div v-if="event" class="mx-auto max-w-6xl px-4 py-6 sm:py-8">
    <RouterLink
      to="/dashboard"
      class="inline-flex items-center gap-1 text-sm font-semibold text-ink-soft transition hover:text-brand-700"
    >
      <span>←</span> Tutti gli eventi
    </RouterLink>

    <!-- ===== HERO HEADER ===== -->
    <div class="relative mt-3 overflow-hidden rounded-3xl shadow-card">
      <!-- Sfondo: copertina immagine oppure gradiente colore -->
      <div class="absolute inset-0">
        <img
          v-if="cover"
          :src="cover"
          :alt="`Copertina di ${event.title}`"
          class="h-full w-full object-cover"
        />
        <div
          v-else
          class="h-full w-full"
          :style="{
            background: `linear-gradient(135deg, ${event.cover_color} 0%, ${event.cover_color}cc 100%)`,
          }"
        />
        <div v-if="!cover" class="absolute inset-0 bg-dots opacity-30" />
      </div>
      <!-- Velo per leggibilità testo -->
      <div class="absolute inset-0 bg-gradient-to-t from-ink/80 via-ink/30 to-ink/10" />

      <!-- Spinner durante l'upload copertina -->
      <div v-if="uploadingCover" class="absolute inset-0 z-10 grid place-items-center bg-ink/40">
        <span class="h-8 w-8 animate-spin rounded-full border-2 border-white/40 border-t-white" />
      </div>

      <div
        class="relative flex min-h-[16rem] flex-col justify-between gap-4 p-6 sm:min-h-[18rem] sm:p-8"
      >
        <!-- Azioni in alto a destra -->
        <div class="flex justify-end gap-2">
          <button
            class="btn bg-white/15 text-white backdrop-blur hover:bg-white/25 px-3 py-2 text-xs sm:text-sm"
            @click="pickCover"
          >
            📷 {{ cover ? 'Cambia copertina' : 'Aggiungi copertina' }}
          </button>
          <button
            v-if="cover"
            class="btn bg-white/15 text-white backdrop-blur hover:bg-error px-3 py-2 text-xs sm:text-sm"
            @click="removeCover"
          >
            Rimuovi
          </button>
          <input
            ref="coverInput"
            type="file"
            class="hidden"
            accept="image/jpeg,image/png,image/webp"
            @change="onCoverChange"
          />
        </div>

        <!-- Titolo + meta -->
        <div>
          <span class="badge bg-white/20 text-white backdrop-blur"
            >{{ event.photo_count }} contenuti</span
          >
          <h1
            class="mt-3 text-3xl font-extrabold text-white drop-shadow-sm sm:text-4xl lg:text-5xl"
          >
            {{ event.title }}
          </h1>
          <p v-if="formattedDate" class="mt-2 text-white/90">📅 {{ formattedDate }}</p>
          <p v-if="event.description" class="mt-2 max-w-2xl text-white/80">
            {{ event.description }}
          </p>
          <div class="mt-5 flex flex-wrap gap-2">
            <button
              class="btn bg-white text-ink hover:bg-brand-50 px-4 py-2 text-sm"
              @click="editOpen = true"
            >
              Modifica evento
            </button>
            <button
              class="btn bg-white/15 text-white backdrop-blur hover:bg-error px-4 py-2 text-sm"
              @click="confirmDelete = true"
            >
              Elimina
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== CORPO: galleria + sidebar sticky ===== -->
    <div class="mt-8 grid gap-8 lg:grid-cols-3">
      <!-- Sidebar (condivisione) -->
      <aside class="order-1 space-y-6 lg:order-2 lg:col-span-1">
        <div class="lg:sticky lg:top-20">
          <QrShare :url="shareUrl" :title="event.title" />
        </div>
      </aside>

      <!-- Galleria (protagonista) -->
      <section class="order-2 lg:order-1 lg:col-span-2">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-xl font-bold">Galleria</h2>
          <!-- Filtri -->
          <div
            class="inline-flex rounded-xl border border-surface-border bg-surface p-1 text-sm font-semibold"
          >
            <button
              class="rounded-lg px-3 py-1.5 transition"
              :class="
                filter === 'all'
                  ? 'bg-brand-gradient text-white shadow-sm'
                  : 'text-ink-soft hover:text-brand-700'
              "
              @click="filter = 'all'"
            >
              Tutti <span class="opacity-70">{{ allPhotos.length }}</span>
            </button>
            <button
              class="rounded-lg px-3 py-1.5 transition"
              :class="
                filter === 'image'
                  ? 'bg-brand-gradient text-white shadow-sm'
                  : 'text-ink-soft hover:text-brand-700'
              "
              @click="filter = 'image'"
            >
              Foto <span class="opacity-70">{{ imageCount }}</span>
            </button>
            <button
              class="rounded-lg px-3 py-1.5 transition"
              :class="
                filter === 'video'
                  ? 'bg-brand-gradient text-white shadow-sm'
                  : 'text-ink-soft hover:text-brand-700'
              "
              @click="filter = 'video'"
            >
              Video <span class="opacity-70">{{ videoCount }}</span>
            </button>
          </div>
        </div>
        <PhotoGrid
          :photos="filteredPhotos"
          :media-base="mediaBase"
          can-delete
          @delete="handleDeletePhoto"
        />

        <!-- Carica altri (paginazione lato server) -->
        <div v-if="hasMorePhotos" class="mt-8 text-center">
          <button class="btn-secondary" :disabled="loadingMore" @click="loadMorePhotos">
            <span
              v-if="loadingMore"
              class="h-4 w-4 animate-spin rounded-full border-2 border-brand-300 border-t-brand-600"
            />
            {{ loadingMore ? 'Caricamento…' : 'Carica altri' }}
          </button>
        </div>
      </section>
    </div>

    <!-- Modale modifica -->
    <EventFormModal
      ref="editRef"
      :open="editOpen"
      :event="event"
      @close="editOpen = false"
      @submit="handleEdit"
    />

    <!-- Conferma eliminazione evento -->
    <transition name="fade">
      <div v-if="confirmDelete" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40 backdrop-blur-sm" @click="confirmDelete = false" />
        <div
          class="relative w-full max-w-md card p-6"
          v-focus-trap="() => (confirmDelete = false)"
          role="dialog"
          aria-modal="true"
          aria-labelledby="confirm-delete-title"
        >
          <h3 id="confirm-delete-title" class="text-lg font-bold">Eliminare questo evento?</h3>
          <p class="mt-2 text-sm text-ink-soft">
            Verranno eliminati anche tutti i contenuti caricati e la copertina. L’azione è
            irreversibile.
          </p>
          <div class="mt-6 flex justify-end gap-3">
            <button class="btn-secondary" @click="confirmDelete = false">Annulla</button>
            <button class="btn-danger" @click="handleDeleteEvent">Elimina definitivamente</button>
          </div>
        </div>
      </div>
    </transition>
  </div>

  <!-- Loading skeleton -->
  <div v-else class="mx-auto max-w-6xl px-4 py-8">
    <div class="h-4 w-32 animate-pulse rounded bg-surface-border" />
    <div class="mt-3 h-64 w-full animate-pulse rounded-3xl bg-surface-border" />
    <div class="mt-8 grid gap-8 lg:grid-cols-3">
      <div class="order-1 h-72 animate-pulse rounded-2xl bg-surface-border lg:order-2" />
      <div class="order-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:order-1 lg:col-span-2">
        <div
          v-for="n in 6"
          :key="n"
          class="aspect-square animate-pulse rounded-xl bg-surface-border"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
