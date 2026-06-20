<script setup>
import { onMounted, ref } from 'vue'
import { vAutoAnimate } from '@formkit/auto-animate/vue'
import { useEventsStore } from '@/stores/events'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { parseApiError } from '@/api/axios'
import EventCard from '@/components/EventCard.vue'
import EventFormModal from '@/components/EventFormModal.vue'
import { fadeUp, stagger } from '@/composables/motion'

const events = useEventsStore()
const auth = useAuthStore()
const ui = useUiStore()

const modalOpen = ref(false)
const modalRef = ref(null)

onMounted(async () => {
  try {
    await events.fetchEvents()
  } catch (e) {
    ui.error(parseApiError(e, 'Impossibile caricare gli eventi').message)
  }
})

async function handleCreate(payload, media = {}) {
  try {
    const event = await events.createEvent(payload)
    // Copertina e/o avatar immagine scelti: li carichiamo sul nuovo evento.
    try {
      if (media.cover?.file) await events.uploadCover(event.id, media.cover.file)
      if (media.avatar?.file) await events.uploadAvatar(event.id, media.avatar.file)
    } catch {
      ui.error('Evento creato, ma il caricamento di un’immagine è fallito')
    }
    ui.success('Evento creato!')
    modalOpen.value = false
    return event
  } catch (e) {
    const { message, fields } = parseApiError(e, 'Creazione fallita')
    modalRef.value?.setFieldErrors(fields)
    modalRef.value?.stopSubmitting()
    ui.error(message)
  }
}
</script>

<template>
  <div class="mx-auto max-w-6xl px-4 py-10">
    <!-- Saluto + CTA -->
    <div
      v-motion="fadeUp"
      class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
      <div>
        <h1 class="text-3xl font-bold sm:text-4xl">Ciao {{ auth.user?.name }} 👋</h1>
        <p class="mt-2 text-ink-soft">Ecco i tuoi eventi e le loro gallerie.</p>
      </div>
      <button
        class="btn btn-lg bg-accent-400 text-white shadow-card hover:bg-accent-500 hover:-translate-y-0.5 hover:shadow-card-hover"
        @click="modalOpen = true"
      >
        <span class="text-lg leading-none">+</span> Nuovo evento
      </button>
    </div>

    <!-- Loading skeleton -->
    <div
      v-if="events.loading && !events.events.length"
      class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
    >
      <div v-for="n in 3" :key="n" class="card overflow-hidden">
        <div class="h-28 w-full animate-pulse bg-surface-muted" />
        <div class="space-y-3 p-5">
          <div class="h-5 w-2/3 animate-pulse rounded bg-surface-muted" />
          <div class="h-4 w-1/3 animate-pulse rounded bg-surface-muted" />
          <div class="h-4 w-full animate-pulse rounded bg-surface-muted" />
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!events.events.length" v-motion="fadeUp" class="mt-16">
      <div class="card mx-auto max-w-lg p-10 text-center">
        <div class="mx-auto grid h-20 w-20 place-items-center rounded-3xl bg-brand-50 text-4xl">
          📸
        </div>
        <h2 class="mt-6 text-xl font-bold">Nessun evento, per ora</h2>
        <p class="mx-auto mt-2 max-w-sm text-ink-soft">
          Crea il tuo primo evento e condividi il QR con gli invitati: le foto arriveranno qui.
        </p>
        <button class="btn-primary mt-7 px-5 py-3" @click="modalOpen = true">
          Crea il primo evento
        </button>
      </div>
    </div>

    <!-- Griglia eventi (auto-animate su add/remove) -->
    <div v-else v-auto-animate class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="(ev, i) in events.events" :key="ev.id" v-motion="stagger(i)">
        <EventCard :event="ev" />
      </div>
    </div>

    <EventFormModal
      ref="modalRef"
      :open="modalOpen"
      @close="modalOpen = false"
      @submit="handleCreate"
    />
  </div>
</template>
