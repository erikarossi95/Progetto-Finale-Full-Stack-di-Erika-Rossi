<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { vAutoAnimate } from '@formkit/auto-animate/vue'
import api, { parseApiError } from '@/api/axios'
import { useUiStore } from '@/stores/ui'

const props = defineProps({
  slug: { type: String, required: true },
  // Quando true, niente card/intestazione proprie: è già dentro un contenitore.
  embedded: { type: Boolean, default: false },
})
const emit = defineEmits(['uploaded', 'state'])

const ui = useUiStore()

// Whitelist client, coerente con la validazione del backend.
const ACCEPT = ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime']
const MAX_BYTES = 25 * 1024 * 1024 // 25 MB

const uploaderName = ref('')
const dragging = ref(false)
const fileInput = ref(null)
const busy = ref(false)
let uid = 0

// File SELEZIONATI ma NON ancora caricati: si pubblicano solo a conferma.
// { id, file, name, preview, isVideo, status: 'pending'|'uploading'|'done'|'error', progress }
const staged = ref([])

const pending = computed(
  () => staged.value.filter((s) => s.status === 'pending' || s.status === 'error').length
)

// Comunica lo stato al genitore (per il bottone di conferma nella modale).
watch(
  [pending, busy, () => staged.value.length],
  () => {
    emit('state', { pending: pending.value, busy: busy.value, total: staged.value.length })
  },
  { immediate: true }
)

function pick() {
  fileInput.value?.click()
}
function onDrop(e) {
  dragging.value = false
  addFiles(e.dataTransfer.files)
}
function onChange(e) {
  addFiles(e.target.files)
  e.target.value = '' // consente di riselezionare lo stesso file
}

// Aggiunge i file alla coda locale (con anteprima). NESSUN upload qui.
function addFiles(fileList) {
  for (const file of Array.from(fileList || [])) {
    if (!ACCEPT.includes(file.type)) {
      ui.error(`"${file.name}": formato non supportato`)
      continue
    }
    if (file.size > MAX_BYTES) {
      ui.error(`"${file.name}": troppo grande (max 25 MB)`)
      continue
    }
    const isVideo = file.type.startsWith('video/')
    staged.value.push({
      id: ++uid,
      file,
      name: file.name,
      preview: isVideo ? null : URL.createObjectURL(file),
      isVideo,
      status: 'pending',
      progress: 0,
    })
  }
}

function removeItem(id) {
  const it = staged.value.find((s) => s.id === id)
  if (it?.preview) URL.revokeObjectURL(it.preview)
  staged.value = staged.value.filter((s) => s.id !== id)
}

// Carica e PUBBLICA tutti i file in coda (chiamato dalla conferma utente).
async function uploadAll() {
  if (busy.value) return
  busy.value = true
  for (const it of staged.value) {
    if (it.status === 'done') continue
    it.status = 'uploading'
    it.progress = 0
    const fd = new FormData()
    fd.append('file', it.file)
    if (uploaderName.value.trim()) fd.append('uploader_name', uploaderName.value.trim())
    try {
      const { data } = await api.post(`/public/events/${props.slug}/photos`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (e) => {
          if (e.total) it.progress = Math.round((e.loaded / e.total) * 100)
        },
      })
      it.status = 'done'
      emit('uploaded', data.data.photo) // pubblicazione ottimistica, ora dopo la conferma
    } catch (e) {
      it.status = 'error'
      ui.error(parseApiError(e, `Caricamento di "${it.name}" fallito`).message)
    }
  }
  busy.value = false
}

function reset() {
  staged.value.forEach((it) => it.preview && URL.revokeObjectURL(it.preview))
  staged.value = []
  uploaderName.value = ''
}

onBeforeUnmount(reset)
defineExpose({ uploadAll, reset })
</script>

<template>
  <div :class="embedded ? '' : 'card p-6'">
    <template v-if="!embedded">
      <h3 class="text-lg font-bold">Carica le tue foto e video</h3>
      <p class="mt-1 text-sm text-ink-soft">JPEG, PNG, WebP, MP4 o MOV — fino a 25 MB ciascuno.</p>
    </template>
    <p v-else class="text-sm text-ink-soft">JPEG, PNG, WebP, MP4 o MOV — fino a 25 MB ciascuno.</p>

    <div class="mt-4">
      <label class="label" for="uploader"
        >Il tuo nome <span class="font-normal text-ink-muted">(facoltativo)</span></label
      >
      <input
        id="uploader"
        v-model="uploaderName"
        type="text"
        maxlength="100"
        class="input"
        placeholder="Es. Marco"
      />
    </div>

    <!-- Dropzone -->
    <div
      class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-10 text-center transition"
      :class="
        dragging
          ? 'border-brand-400 bg-brand-50'
          : 'border-surface-border hover:border-brand-300 hover:bg-brand-50/50'
      "
      @click="pick"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <div
        class="grid h-14 w-14 place-items-center rounded-2xl bg-brand-gradient text-white shadow-card"
      >
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          class="h-6 w-6"
          aria-hidden="true"
        >
          <path d="M12 16V4" />
          <path d="m6 10 6-6 6 6" />
          <path d="M4 20h16" />
        </svg>
      </div>
      <p class="mt-4 font-semibold text-ink">
        Trascina qui i file o <span class="text-brand-600">scegli dal dispositivo</span>
      </p>
      <p class="mt-1 text-sm text-ink-muted">Puoi selezionarne più di uno</p>
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        multiple
        accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime"
        @change="onChange"
      />
    </div>

    <!-- File selezionati (anteprima). Si pubblicano solo alla conferma. -->
    <ul v-if="staged.length" v-auto-animate class="mt-4 space-y-2">
      <li v-for="it in staged" :key="it.id" class="rounded-xl bg-surface-muted px-3 py-2.5 text-sm">
        <div class="flex items-center gap-3">
          <span
            class="relative grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-white text-sm shadow-sm"
          >
            <img v-if="it.preview" :src="it.preview" alt="" class="h-full w-full object-cover" />
            <span v-else-if="it.isVideo" aria-hidden="true">🎬</span>
            <span v-else aria-hidden="true">📎</span>
          </span>
          <span class="flex-1 truncate font-medium text-ink">{{ it.name }}</span>

          <!-- Stato a destra -->
          <span v-if="it.status === 'uploading'" class="shrink-0 text-ink-muted"
            >{{ it.progress }}%</span
          >
          <span v-else-if="it.status === 'done'" class="shrink-0 font-semibold text-success-dark"
            >✓</span
          >
          <span v-else-if="it.status === 'error'" class="shrink-0 font-semibold text-error-dark"
            >Errore</span
          >
          <button
            v-else
            class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-lg text-ink-muted transition hover:bg-surface-border hover:text-error"
            aria-label="Rimuovi file"
            @click="removeItem(it.id)"
          >
            ×
          </button>
        </div>
        <div
          v-if="it.status === 'uploading'"
          class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-border"
        >
          <div
            class="h-full rounded-full bg-brand-gradient transition-all duration-200 ease-snappy"
            :style="{ width: it.progress + '%' }"
          />
        </div>
      </li>
    </ul>
  </div>
</template>
