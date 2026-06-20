<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import api, { parseApiError } from '@/api/axios'
import { mediaBaseUrl, coverUrl } from '@/utils/url'
import { initialOf } from '@/utils/format'
import AppLogo from '@/components/AppLogo.vue'
import UploadDropzone from '@/components/UploadDropzone.vue'
import EventGallery from '@/components/EventGallery.vue'
import { fadeUp } from '@/composables/motion'

const route = useRoute()
const slug = route.params.slug

const event = ref(null)
const photos = ref([])
const photosTotal = ref(0)
const page = ref(1)
const loadingMore = ref(false)
const loading = ref(true)
const notFound = ref(false)

const hasMore = computed(() => photos.value.length < photosTotal.value)
const mediaBase = mediaBaseUrl()
const cover = computed(() => coverUrl(event.value?.cover_image_url))

const uploadOpen = ref(false)
const view = ref('grid') // 'grid' | 'feed'

// Upload: riferimento alla dropzone + stato (per il bottone di conferma).
const dz = ref(null)
const dzState = ref({ pending: 0, busy: false, total: 0 })
function onDzState(s) {
  dzState.value = s
}
function closeUpload() {
  uploadOpen.value = false
  dz.value?.reset()
}

const initial = computed(() => initialOf(event.value?.title))
const avatarImg = computed(() => coverUrl(event.value?.avatar_image_url))
const avatarEmoji = computed(() => event.value?.avatar_emoji || null)
// Avatar evento: gradiente derivato dal cover_color (brand di default).
const avatarStyle = computed(() => {
  const c = event.value?.cover_color || '#6c5ce7'
  return { background: `linear-gradient(135deg, ${c} 0%, ${c}99 100%)` }
})

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
  loading.value = true
  try {
    const { data } = await api.get(`/public/events/${slug}`)
    event.value = data.data.event
    photos.value = data.data.event.photos || []
    photosTotal.value = data.data.event.photos_total ?? photos.value.length
    page.value = 1
  } catch (e) {
    if (parseApiError(e).code === 'NOT_FOUND') notFound.value = true
  } finally {
    loading.value = false
  }
}

// Carica la pagina successiva di media dal server e la appende.
async function loadMore() {
  if (loadingMore.value || !hasMore.value) return
  loadingMore.value = true
  try {
    const { data } = await api.get(`/public/events/${slug}/photos`, {
      params: { page: page.value + 1 },
    })
    photos.value.push(...data.data.photos)
    photosTotal.value = data.data.total
    page.value += 1
  } catch {
    /* errore non bloccante */
  } finally {
    loadingMore.value = false
  }
}

// Optimistic UI: la nuova foto compare subito in cima (pop-in via auto-animate).
function onUploaded(photo) {
  photos.value.unshift(photo)
  photosTotal.value += 1
}
</script>

<template>
  <!-- ===== 404 gentile ===== -->
  <div v-if="notFound" class="grid min-h-screen place-items-center px-4 text-center">
    <div v-motion="fadeUp">
      <p class="text-6xl">🔍</p>
      <h1 class="mt-4 text-2xl font-bold">Evento non trovato</h1>
      <p class="mt-2 text-ink-soft">Il link potrebbe essere errato o l’evento è stato rimosso.</p>
      <RouterLink to="/" class="btn-primary mt-6">Vai a Snaply</RouterLink>
    </div>
  </div>

  <!-- ===== Loading skeleton ===== -->
  <div v-else-if="loading" class="min-h-screen">
    <div class="h-40 w-full animate-pulse bg-surface-border sm:h-52" />
    <div class="mx-auto max-w-3xl px-4">
      <div
        class="-mt-12 h-24 w-24 animate-pulse rounded-full border-4 border-surface-muted bg-surface-border"
      />
      <div class="mt-4 h-7 w-2/3 animate-pulse rounded bg-surface-border" />
      <div class="mt-3 h-4 w-1/3 animate-pulse rounded bg-surface-border" />
      <div class="mt-8 grid grid-cols-3 gap-1 sm:gap-2">
        <div
          v-for="n in 9"
          :key="n"
          class="aspect-square animate-pulse rounded-md bg-surface-border sm:rounded-lg"
        />
      </div>
    </div>
  </div>

  <!-- ===== Pagina (profilo social) ===== -->
  <div v-else class="min-h-screen pb-28">
    <!-- Banner cover -->
    <div
      class="relative h-40 w-full overflow-hidden sm:h-52"
      :style="
        !cover
          ? {
              background: `linear-gradient(135deg, ${event.cover_color} 0%, ${event.cover_color}cc 100%)`,
            }
          : {}
      "
    >
      <img
        v-if="cover"
        :src="cover"
        :alt="`Copertina di ${event.title}`"
        class="h-full w-full object-cover"
      />
      <!-- Velo leggero solo in alto, per la leggibilità del logo: la copertina resta protagonista -->
      <div
        v-if="cover"
        class="absolute inset-0 bg-gradient-to-b from-ink/40 via-transparent to-transparent"
      />
      <div v-else class="absolute inset-0 bg-dots opacity-30" />
      <div class="absolute inset-x-0 top-0 mx-auto flex max-w-3xl items-center px-4 py-4">
        <RouterLink to="/"><AppLogo light /></RouterLink>
      </div>
    </div>

    <div class="mx-auto max-w-3xl px-4">
      <!-- HEADER PROFILO -->
      <div v-motion="fadeUp" class="flex flex-col gap-4 sm:flex-row sm:items-end">
        <div
          class="-mt-12 grid h-24 w-24 shrink-0 place-items-center overflow-hidden rounded-full border-4 border-surface-muted text-3xl font-extrabold text-white shadow-card sm:-mt-14 sm:h-28 sm:w-28"
          :style="!avatarImg ? avatarStyle : {}"
        >
          <img
            v-if="avatarImg"
            :src="avatarImg"
            :alt="`Avatar di ${event.title}`"
            class="h-full w-full object-cover"
          />
          <span v-else-if="avatarEmoji" class="text-4xl">{{ avatarEmoji }}</span>
          <span v-else>{{ initial }}</span>
        </div>
        <div class="min-w-0 flex-1">
          <h1 class="font-display text-2xl font-extrabold sm:text-3xl">{{ event.title }}</h1>
          <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink-soft">
            <span
              ><span class="font-bold text-ink">{{ photos.length }}</span>
              {{ photos.length === 1 ? 'ricordo' : 'ricordi' }}</span
            >
            <span v-if="formattedDate">📅 {{ formattedDate }}</span>
          </div>
          <p v-if="event.description" class="mt-2 max-w-xl text-sm text-ink-soft">
            {{ event.description }}
          </p>
          <p class="mt-1 text-sm font-medium text-brand-600">
            Aggiungi i tuoi scatti a questo evento
          </p>
        </div>
      </div>

      <!-- CTA upload -->
      <button
        class="btn-accent group mt-6 w-full gap-2.5 py-3.5 text-base shadow-card-hover sm:w-auto sm:px-8"
        @click="uploadOpen = true"
      >
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          class="h-5 w-5 transition-transform duration-300 ease-snappy group-hover:scale-110"
          aria-hidden="true"
        >
          <path
            d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3Z"
          />
          <circle cx="12" cy="13" r="3.2" />
        </svg>
        Carica le tue foto
      </button>

      <!-- Toggle vista -->
      <div class="mt-8 flex items-center justify-center border-t border-surface-border pt-3">
        <div
          class="inline-flex rounded-xl border border-surface-border bg-surface p-1 text-sm font-semibold"
        >
          <button
            class="flex items-center gap-1.5 rounded-lg px-4 py-2 transition"
            :class="
              view === 'grid'
                ? 'bg-brand-gradient text-white shadow-sm'
                : 'text-ink-soft hover:text-brand-700'
            "
            @click="view = 'grid'"
            aria-label="Vista griglia"
          >
            ▦ Griglia
          </button>
          <button
            class="flex items-center gap-1.5 rounded-lg px-4 py-2 transition"
            :class="
              view === 'feed'
                ? 'bg-brand-gradient text-white shadow-sm'
                : 'text-ink-soft hover:text-brand-700'
            "
            @click="view = 'feed'"
            aria-label="Vista feed"
          >
            ☰ Feed
          </button>
        </div>
      </div>

      <!-- Galleria / empty -->
      <div class="mt-5">
        <div v-if="!photos.length" class="card flex flex-col items-center px-6 py-16 text-center">
          <div class="grid h-20 w-20 place-items-center rounded-3xl bg-brand-50 text-4xl">✨</div>
          <h3 class="mt-6 text-lg font-bold">Sii il primo a condividere un ricordo!</h3>
          <p class="mx-auto mt-2 max-w-sm text-ink-soft">
            Le foto e i video che carichi appariranno subito qui, per tutti gli invitati.
          </p>
          <button class="btn-primary mt-6" @click="uploadOpen = true">Carica ora</button>
        </div>

        <EventGallery
          v-else
          :photos="photos"
          :media-base="mediaBase"
          :view="view"
          :cover="cover"
          :has-more="hasMore"
          :loading-more="loadingMore"
          @load-more="loadMore"
        />
      </div>
    </div>

    <!-- Footer -->
    <footer class="mt-20 border-t border-surface-border bg-surface">
      <div class="mx-auto flex max-w-3xl flex-col items-center gap-3 px-4 py-10 text-center">
        <RouterLink to="/" class="inline-flex"><AppLogo /></RouterLink>
        <p class="text-sm text-ink-soft">
          Vuoi una galleria così per il tuo evento?
          <RouterLink to="/" class="font-semibold text-brand-600 hover:text-brand-700"
            >Crea il tuo evento con Snaply →</RouterLink
          >
        </p>
      </div>
    </footer>

    <!-- FAB carica (sempre raggiungibile) -->
    <button
      class="fixed bottom-6 right-6 z-40 flex items-center gap-2 rounded-full bg-accent-400 px-5 py-4 font-semibold text-white shadow-card-hover transition ease-snappy hover:bg-accent-500 hover:-translate-y-0.5 active:scale-95"
      aria-label="Carica foto o video"
      @click="uploadOpen = true"
    >
      <span class="text-xl leading-none">＋</span>
      <span class="hidden sm:inline">Carica</span>
    </button>

    <!-- Modale upload (bottom sheet su mobile) -->
    <transition name="sheet">
      <div
        v-if="uploadOpen"
        class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
      >
        <div class="absolute inset-0 bg-ink/50 backdrop-blur-sm" @click="closeUpload" />
        <div class="sheet-panel relative w-full max-w-lg">
          <div
            class="card max-h-[88vh] overflow-y-auto rounded-b-none p-5 sm:rounded-2xl sm:p-6"
            v-focus-trap="closeUpload"
            role="dialog"
            aria-modal="true"
            aria-labelledby="upload-sheet-title"
          >
            <div class="mb-4 flex items-center justify-between">
              <h3 id="upload-sheet-title" class="text-lg font-bold">Aggiungi i tuoi ricordi</h3>
              <button
                class="grid h-11 w-11 place-items-center rounded-full text-xl text-ink-muted hover:bg-surface-muted"
                aria-label="Chiudi"
                @click="closeUpload"
              >
                ×
              </button>
            </div>

            <UploadDropzone
              ref="dz"
              :slug="slug"
              embedded
              @uploaded="onUploaded"
              @state="onDzState"
            />

            <!-- Conferma: i file si pubblicano SOLO premendo qui, non alla selezione -->
            <div class="mt-5 flex gap-3">
              <button class="btn-secondary flex-1" @click="closeUpload">
                {{ dzState.total ? 'Annulla' : 'Chiudi' }}
              </button>
              <button
                v-if="dzState.pending > 0 || dzState.busy"
                class="btn-primary flex-1"
                :disabled="dzState.busy"
                @click="dz?.uploadAll()"
              >
                <span
                  v-if="dzState.busy"
                  class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                />
                {{
                  dzState.busy
                    ? 'Pubblicazione…'
                    : `Pubblica ${dzState.pending} ${dzState.pending === 1 ? 'file' : 'file'}`
                }}
              </button>
              <button v-else-if="dzState.total > 0" class="btn-primary flex-1" @click="closeUpload">
                Fatto
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.sheet-enter-active,
.sheet-leave-active {
  transition: opacity 0.25s ease;
}
.sheet-enter-from,
.sheet-leave-to {
  opacity: 0;
}
.sheet-enter-active .sheet-panel,
.sheet-leave-active .sheet-panel {
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.sheet-enter-from .sheet-panel,
.sheet-leave-to .sheet-panel {
  transform: translateY(24px);
}
</style>
