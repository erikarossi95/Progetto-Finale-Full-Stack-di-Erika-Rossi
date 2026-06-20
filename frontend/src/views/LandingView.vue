<script setup>
import { RouterLink } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import { fadeUp, stagger, heroStep } from '@/composables/motion'

const steps = [
  {
    icon: '🎉',
    title: 'Crea l’evento',
    text: 'Dai un nome, una data e un colore. Bastano pochi secondi.',
  },
  {
    icon: '🔗',
    title: 'Condividi il QR',
    text: 'Gli invitati inquadrano il codice. Niente app, niente account.',
  },
  {
    icon: '🖼️',
    title: 'Ricevi tutto',
    text: 'Foto e video arrivano in un’unica galleria, in tempo reale.',
  },
]

const useCases = [
  {
    emoji: '💍',
    title: 'Matrimoni',
    text: 'Ogni sguardo, dietro le quinte e brindisi, raccolti dagli ospiti.',
  },
  {
    emoji: '🎂',
    title: 'Compleanni',
    text: 'Le foto spontanee della festa, senza chiederle una per una.',
  },
  {
    emoji: '🎓',
    title: 'Lauree',
    text: 'Il lancio del tocco e gli abbracci, da ogni angolazione.',
  },
  {
    emoji: '🏢',
    title: 'Eventi aziendali',
    text: 'Team building e conferenze, documentati da chi c’era.',
  },
]
</script>

<template>
  <div>
    <!-- ============ HERO ============ -->
    <section class="relative overflow-hidden bg-brand-gradient">
      <div class="absolute inset-0 bg-dots opacity-60" />
      <div class="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-white/10 blur-3xl" />
      <div class="absolute -right-20 top-10 h-72 w-72 rounded-full bg-accent-400/30 blur-3xl" />

      <div
        class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-20 sm:py-28 lg:grid-cols-2"
      >
        <!-- Copy -->
        <div class="text-center lg:text-left">
          <span v-motion="heroStep(0)" class="badge bg-white/15 text-white backdrop-blur">
            📸 La galleria condivisa dei tuoi eventi
          </span>
          <h1
            v-motion="heroStep(1)"
            class="mt-6 text-4xl font-extrabold leading-[1.1] text-white sm:text-5xl lg:text-6xl"
          >
            Ogni scatto dei tuoi ospiti, in un unico posto
          </h1>
          <p v-motion="heroStep(2)" class="mx-auto mt-6 max-w-xl text-lg text-white/85 lg:mx-0">
            Con Snaply gli invitati caricano foto e video con un semplice QR code. Senza installare
            nulla, senza registrarsi. Tu ritrovi tutto nella tua galleria.
          </p>
          <div
            v-motion="heroStep(3)"
            class="mt-10 flex flex-wrap items-center justify-center gap-3 lg:justify-start"
          >
            <RouterLink
              to="/register"
              class="btn btn-lg bg-accent-400 text-white shadow-card-hover hover:bg-accent-500 hover:-translate-y-0.5"
            >
              Crea il tuo evento
            </RouterLink>
            <RouterLink
              to="/login"
              class="btn btn-lg border border-white/40 bg-white/10 text-white hover:bg-white/20"
            >
              Accedi
            </RouterLink>
          </div>
          <p v-motion="heroStep(4)" class="mt-6 text-sm text-white/70">
            Gratis per iniziare · Nessuna carta richiesta
          </p>
        </div>

        <!-- Visual mockup leggero (CSS, niente immagini pesanti) -->
        <div v-motion="heroStep(2)" class="relative mx-auto w-full max-w-sm">
          <div
            class="card rotate-2 p-5 shadow-card-hover transition duration-500 ease-snappy hover:rotate-0"
          >
            <div class="flex items-center gap-2">
              <span class="h-2.5 w-2.5 rounded-full bg-accent-300" />
              <span class="h-2.5 w-2.5 rounded-full bg-brand-200" />
              <span class="h-2.5 w-2.5 rounded-full bg-surface-border" />
              <span class="ml-2 text-xs font-semibold text-ink-muted"
                >snaply.app/e/luca-e-sara</span
              >
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2">
              <div
                v-for="i in 9"
                :key="i"
                class="aspect-square rounded-xl"
                :class="[
                  i % 4 === 0 ? 'bg-accent-200' : i % 3 === 0 ? 'bg-brand-300' : 'bg-brand-100',
                ]"
              />
            </div>
            <div
              class="mt-4 flex items-center justify-between rounded-xl bg-surface-muted px-3 py-2"
            >
              <span class="text-sm font-semibold text-ink">42 contenuti</span>
              <span class="badge bg-success-light text-success-dark">● live</span>
            </div>
          </div>
          <!-- QR fluttuante -->
          <div
            class="absolute -bottom-6 -left-6 grid h-24 w-24 place-items-center rounded-2xl bg-white p-2 shadow-card-hover"
          >
            <div class="grid h-full w-full grid-cols-4 grid-rows-4 gap-0.5">
              <span
                v-for="n in 16"
                :key="n"
                class="rounded-[2px]"
                :class="[(n * 7) % 3 === 0 ? 'bg-ink' : 'bg-transparent']"
              />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ COME FUNZIONA ============ -->
    <section class="mx-auto max-w-6xl px-4 py-24">
      <div v-motion="fadeUp" class="text-center">
        <span class="eyebrow">Semplice davvero</span>
        <h2 class="mt-3 text-3xl font-bold sm:text-4xl">Come funziona</h2>
        <p class="mx-auto mt-3 max-w-lg text-ink-soft">
          Tre passi, zero attrito per i tuoi ospiti.
        </p>
      </div>

      <div class="mt-14 grid gap-6 sm:grid-cols-3">
        <div
          v-for="(s, i) in steps"
          :key="i"
          v-motion="stagger(i)"
          class="card-interactive p-7 text-center"
        >
          <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-3xl">
            {{ s.icon }}
          </div>
          <div class="mt-5 text-xs font-bold text-brand-400">PASSO {{ i + 1 }}</div>
          <h3 class="mt-1 text-lg font-bold">{{ s.title }}</h3>
          <p class="mt-2 text-sm text-ink-soft">{{ s.text }}</p>
        </div>
      </div>
    </section>

    <!-- ============ CASI D'USO ============ -->
    <section class="bg-surface">
      <div class="mx-auto max-w-6xl px-4 py-24">
        <div v-motion="fadeUp" class="text-center">
          <span class="eyebrow">Per ogni occasione</span>
          <h2 class="mt-3 text-3xl font-bold sm:text-4xl">Fatto per i momenti che contano</h2>
        </div>
        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="(u, i) in useCases"
            :key="i"
            v-motion="stagger(i)"
            class="group rounded-2xl border border-surface-border bg-surface-muted p-7 transition-all duration-300 ease-snappy hover:-translate-y-1 hover:bg-surface hover:shadow-card"
          >
            <div
              class="grid h-12 w-12 place-items-center rounded-xl bg-white text-2xl shadow-sm transition group-hover:scale-110"
            >
              {{ u.emoji }}
            </div>
            <h3 class="mt-5 text-lg font-bold">{{ u.title }}</h3>
            <p class="mt-2 text-sm text-ink-soft">{{ u.text }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ CTA FINALE ============ -->
    <section class="mx-auto max-w-4xl px-4 py-24">
      <div
        v-motion="fadeUp"
        class="card relative overflow-hidden bg-brand-warm p-12 text-center text-white"
      >
        <div class="absolute inset-0 bg-dots opacity-40" />
        <div class="relative">
          <h2 class="text-3xl font-bold text-white sm:text-4xl">
            Pronto a non perdere più nessuno scatto?
          </h2>
          <p class="mx-auto mt-4 max-w-xl text-white/85">
            Crea il tuo primo evento adesso. È gratis e ci metti meno di un minuto.
          </p>
          <RouterLink
            to="/register"
            class="btn btn-lg mt-8 bg-white text-accent-600 shadow-card-hover hover:bg-accent-50 hover:-translate-y-0.5"
          >
            Inizia gratis
          </RouterLink>
        </div>
      </div>
    </section>

    <!-- ============ FOOTER ============ -->
    <footer class="border-t border-surface-border bg-surface">
      <div
        class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 py-10 sm:flex-row"
      >
        <AppLogo />
        <p class="text-sm text-ink-muted">
          © {{ new Date().getFullYear() }} Snaply — la galleria condivisa dei tuoi eventi.
        </p>
      </div>
    </footer>
  </div>
</template>
