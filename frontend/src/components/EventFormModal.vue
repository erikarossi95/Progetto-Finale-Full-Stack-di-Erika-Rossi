<script setup>
import { reactive, ref, watch, computed, onBeforeUnmount } from 'vue'
import { useUiStore } from '@/stores/ui'
import { coverUrl } from '@/utils/url'
import { initialOf } from '@/utils/format'

const props = defineProps({
  open: { type: Boolean, default: false },
  // Se passato, il modal è in modalità modifica.
  event: { type: Object, default: null },
})
const emit = defineEmits(['close', 'submit'])

const ui = useUiStore()

// Palette di colori suggeriti (token brand/accent + qualche tinta calda).
const palette = [
  '#6c5ce7',
  '#9580ea',
  '#ff7675',
  '#10b981',
  '#3b82f6',
  '#f59e0b',
  '#e43535',
  '#1f2233',
]

const form = reactive({
  title: '',
  description: '',
  event_date: '',
  cover_color: '#6c5ce7',
  avatar_emoji: null,
})
const errors = reactive({ title: '', description: '', cover_color: '' })
const submitting = ref(false)

// --- Avatar evento (emoji a tema OPPURE immagine; fallback all'iniziale) ---
const EMOJI_CHOICES = ['💍', '🎂', '🎉', '🎓', '🥂', '👶', '🎄', '🥳', '📸', '🎈', '🏆', '🌹']
const avatarFile = ref(null)
const avatarPreview = ref('')
const removeAvatarImage = ref(false)
const avatarInput = ref(null)

// Immagine avatar da mostrare: nuova selezione > esistente (se non rimossa).
const shownAvatarImage = computed(() => {
  if (avatarPreview.value) return avatarPreview.value
  if (!removeAvatarImage.value && props.event?.avatar_image_url)
    return coverUrl(props.event.avatar_image_url)
  return ''
})
const titleInitial = computed(() => initialOf(form.title))

function revokeAvatarPreview() {
  if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value)
  avatarPreview.value = ''
}
// Emoji e immagine sono mutuamente esclusive nella scelta.
function pickEmoji(e) {
  form.avatar_emoji = form.avatar_emoji === e ? null : e
  if (form.avatar_emoji) {
    revokeAvatarPreview()
    avatarFile.value = null
    removeAvatarImage.value = !!props.event?.avatar_image_url
  }
}
function pickAvatarImage() {
  avatarInput.value?.click()
}
function onAvatarChange(ev) {
  const file = ev.target.files?.[0]
  ev.target.value = ''
  if (!file) return
  if (!COVER_MIME.includes(file.type)) return ui.error('Avatar: usa un’immagine JPG, PNG o WebP')
  if (file.size > COVER_MAX) return ui.error('Avatar troppo grande (max 25 MB)')
  revokeAvatarPreview()
  avatarFile.value = file
  avatarPreview.value = URL.createObjectURL(file)
  removeAvatarImage.value = false
  form.avatar_emoji = null // l'immagine ha la priorità: niente emoji
}
function clearAvatarImage() {
  revokeAvatarPreview()
  avatarFile.value = null
  removeAvatarImage.value = !!props.event?.avatar_image_url
}

// --- Copertina ---
const COVER_MIME = ['image/jpeg', 'image/png', 'image/webp']
const COVER_MAX = 25 * 1024 * 1024
const coverFile = ref(null) // nuovo file selezionato
const coverPreview = ref('') // objectURL dell'anteprima locale
const removeCover = ref(false) // in modifica: rimuovere quella esistente
const coverInput = ref(null)

// Anteprima da mostrare: nuova selezione > copertina esistente (se non rimossa).
const shownCover = computed(() => {
  if (coverPreview.value) return coverPreview.value
  if (!removeCover.value && props.event?.cover_image_url)
    return coverUrl(props.event.cover_image_url)
  return ''
})

function revokePreview() {
  if (coverPreview.value) URL.revokeObjectURL(coverPreview.value)
  coverPreview.value = ''
}

function pickCover() {
  coverInput.value?.click()
}

function onCoverChange(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return
  if (!COVER_MIME.includes(file.type)) {
    ui.error('Copertina: usa un’immagine JPG, PNG o WebP')
    return
  }
  if (file.size > COVER_MAX) {
    ui.error('Copertina troppo grande (max 25 MB)')
    return
  }
  revokePreview()
  coverFile.value = file
  coverPreview.value = URL.createObjectURL(file)
  removeCover.value = false
}

function clearCover() {
  revokePreview()
  coverFile.value = null
  // In modifica segnaliamo la rimozione di quella esistente.
  removeCover.value = !!props.event?.cover_image_url
}

onBeforeUnmount(() => {
  revokePreview()
  revokeAvatarPreview()
})

// Reinizializza il form ogni volta che si apre.
watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    form.title = props.event?.title || ''
    form.description = props.event?.description || ''
    form.event_date = props.event?.event_date || ''
    form.cover_color = props.event?.cover_color || '#6c5ce7'
    form.avatar_emoji = props.event?.avatar_emoji || null
    errors.title = errors.description = errors.cover_color = ''
    revokePreview()
    coverFile.value = null
    removeCover.value = false
    revokeAvatarPreview()
    avatarFile.value = null
    removeAvatarImage.value = false
  }
)

function validate() {
  const t = form.title.trim()
  errors.title = t.length < 2 || t.length > 150 ? 'Il titolo deve avere tra 2 e 150 caratteri' : ''
  errors.description = form.description.length > 2000 ? 'Massimo 2000 caratteri' : ''
  errors.cover_color = !/^#[0-9a-fA-F]{6}$/.test(form.cover_color) ? 'Colore non valido' : ''
  return !errors.title && !errors.description && !errors.cover_color
}

async function onSubmit() {
  if (!validate()) return
  submitting.value = true
  try {
    // Il padre gestisce la chiamata API. Passiamo anche l'intento copertina:
    // file = nuova immagine da caricare, remove = rimuovere quella esistente.
    await emit(
      'submit',
      {
        title: form.title.trim(),
        description: form.description.trim(),
        event_date: form.event_date || null,
        cover_color: form.cover_color,
        avatar_emoji: form.avatar_emoji,
      },
      {
        cover: { file: coverFile.value, remove: removeCover.value },
        avatar: { file: avatarFile.value, remove: removeAvatarImage.value },
      }
    )
  } finally {
    submitting.value = false
  }
}

// Espone un setter di errori server-side al padre.
function setFieldErrors(fields = {}) {
  Object.assign(errors, fields)
}
defineExpose({ setFieldErrors, stopSubmitting: () => (submitting.value = false) })
</script>

<template>
  <transition name="modal">
    <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
      <div class="absolute inset-0 bg-ink/40 backdrop-blur-sm" @click="emit('close')" />
      <div
        class="modal-panel relative w-full max-w-lg card max-h-[92vh] overflow-y-auto p-6 sm:p-8"
        v-focus-trap="() => emit('close')"
        role="dialog"
        aria-modal="true"
        aria-labelledby="event-modal-title"
      >
        <h2 id="event-modal-title" class="text-xl font-bold">
          {{ event ? 'Modifica evento' : 'Nuovo evento' }}
        </h2>

        <form class="mt-6 space-y-5" novalidate @submit.prevent="onSubmit">
          <div>
            <label class="label" for="ev-title">Titolo *</label>
            <input
              id="ev-title"
              v-model="form.title"
              type="text"
              class="input"
              :class="errors.title && 'input-error'"
              placeholder="Es. Matrimonio Luca & Sara"
            />
            <p v-if="errors.title" class="field-error" role="alert">{{ errors.title }}</p>
          </div>

          <!-- Avatar evento: emoji a tema oppure immagine (fallback iniziale) -->
          <div>
            <label class="label">Avatar dell’evento</label>
            <div class="flex items-start gap-4">
              <!-- Anteprima circolare -->
              <div
                class="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-full border border-surface-border text-3xl font-extrabold text-white shadow-card"
                :style="
                  !shownAvatarImage
                    ? {
                        background: `linear-gradient(135deg, ${form.cover_color} 0%, ${form.cover_color}99 100%)`,
                      }
                    : {}
                "
              >
                <img
                  v-if="shownAvatarImage"
                  :src="shownAvatarImage"
                  alt="Anteprima avatar"
                  class="h-full w-full object-cover"
                />
                <span v-else-if="form.avatar_emoji">{{ form.avatar_emoji }}</span>
                <span v-else>{{ titleInitial }}</span>
              </div>

              <div class="min-w-0 flex-1">
                <!-- Emoji picker -->
                <div class="flex flex-wrap gap-1.5">
                  <button
                    v-for="e in EMOJI_CHOICES"
                    :key="e"
                    type="button"
                    class="grid h-10 w-10 place-items-center rounded-lg text-xl transition"
                    :class="
                      form.avatar_emoji === e
                        ? 'bg-brand-100 ring-2 ring-brand-400'
                        : 'bg-surface-muted hover:bg-brand-50'
                    "
                    :aria-label="`Emoji ${e}`"
                    :aria-pressed="form.avatar_emoji === e"
                    @click="pickEmoji(e)"
                  >
                    {{ e }}
                  </button>
                </div>
                <!-- Oppure immagine -->
                <div class="mt-2 flex items-center gap-2 text-sm">
                  <button
                    type="button"
                    class="font-semibold text-brand-600 hover:text-brand-700"
                    @click="pickAvatarImage"
                  >
                    {{ shownAvatarImage ? 'Cambia immagine' : 'oppure carica un’immagine' }}
                  </button>
                  <button
                    v-if="shownAvatarImage"
                    type="button"
                    class="text-ink-muted hover:text-error"
                    @click="clearAvatarImage"
                  >
                    Rimuovi
                  </button>
                </div>
                <p class="mt-1 text-xs text-ink-muted">
                  Se non scegli nulla, usiamo l’iniziale del titolo.
                </p>
                <input
                  ref="avatarInput"
                  type="file"
                  class="hidden"
                  accept="image/jpeg,image/png,image/webp"
                  @change="onAvatarChange"
                />
              </div>
            </div>
          </div>

          <div>
            <label class="label" for="ev-desc">Descrizione</label>
            <textarea
              id="ev-desc"
              v-model="form.description"
              rows="3"
              class="input"
              :class="errors.description && 'input-error'"
              placeholder="Due righe sull’evento (opzionale)"
            ></textarea>
            <p v-if="errors.description" class="field-error" role="alert">
              {{ errors.description }}
            </p>
          </div>

          <div>
            <label class="label" for="ev-date">Data</label>
            <input id="ev-date" v-model="form.event_date" type="date" class="input" />
          </div>

          <!-- Copertina (immagine) -->
          <div>
            <label class="label">Immagine di copertina</label>
            <div
              v-if="shownCover"
              class="group relative overflow-hidden rounded-xl border border-surface-border"
            >
              <img :src="shownCover" alt="Anteprima copertina" class="h-36 w-full object-cover" />
              <div class="absolute inset-0 bg-ink/0 transition group-hover:bg-ink/30" />
              <div class="absolute right-2 top-2 flex gap-2">
                <button
                  type="button"
                  class="btn bg-white/90 text-ink px-3 py-1.5 text-xs shadow hover:bg-white"
                  @click="pickCover"
                >
                  Cambia
                </button>
                <button
                  type="button"
                  class="btn bg-white/90 text-error px-3 py-1.5 text-xs shadow hover:bg-error hover:text-white"
                  @click="clearCover"
                >
                  Rimuovi
                </button>
              </div>
            </div>
            <button
              v-else
              type="button"
              @click="pickCover"
              class="flex w-full flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-surface-border py-7 text-center transition hover:border-brand-300 hover:bg-brand-50/50"
            >
              <span class="text-2xl">🖼️</span>
              <span class="text-sm font-semibold text-ink">Aggiungi una copertina</span>
              <span class="text-xs text-ink-muted">JPG, PNG o WebP · opzionale</span>
            </button>
            <input
              ref="coverInput"
              type="file"
              class="hidden"
              accept="image/jpeg,image/png,image/webp"
              @change="onCoverChange"
            />
            <p class="mt-1.5 text-xs text-ink-muted">
              Se non imposti un’immagine, viene usato il colore qui sotto.
            </p>
          </div>

          <div>
            <label class="label"
              >Colore copertina
              <span class="font-normal text-ink-muted">(usato se non c’è immagine)</span></label
            >
            <div class="flex flex-wrap items-center gap-2">
              <button
                v-for="c in palette"
                :key="c"
                type="button"
                class="h-8 w-8 rounded-full border-2 transition"
                :class="
                  form.cover_color.toLowerCase() === c.toLowerCase()
                    ? 'border-ink scale-110'
                    : 'border-white shadow'
                "
                :style="{ backgroundColor: c }"
                :aria-label="c"
                @click="form.cover_color = c"
              />
              <input
                v-model="form.cover_color"
                type="color"
                class="h-8 w-10 cursor-pointer rounded border-0 bg-transparent p-0"
              />
            </div>
            <p v-if="errors.cover_color" class="field-error" role="alert">
              {{ errors.cover_color }}
            </p>
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="btn-secondary" @click="emit('close')">Annulla</button>
            <button type="submit" class="btn-primary" :disabled="submitting">
              {{ submitting ? 'Salvataggio…' : event ? 'Salva modifiche' : 'Crea evento' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.22s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
/* Il pannello entra con un leggero scale + slide, oltre al fade dell'overlay. */
.modal-enter-active .modal-panel {
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-enter-from .modal-panel {
  transform: translateY(16px) scale(0.97);
}
</style>
