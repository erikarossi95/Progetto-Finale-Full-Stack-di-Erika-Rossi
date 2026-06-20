<script setup>
import { ref, watch, onMounted } from 'vue'
import QRCode from 'qrcode'
import { useUiStore } from '@/stores/ui'

const props = defineProps({
  // URL pubblico completo dell'evento (es. http://localhost:5173/e/abc123).
  url: { type: String, required: true },
  title: { type: String, default: '' },
})

const ui = useUiStore()
const dataUrl = ref('')
const copied = ref(false)

async function render() {
  if (!props.url) return
  try {
    dataUrl.value = await QRCode.toDataURL(props.url, {
      width: 320,
      margin: 1,
      color: { dark: '#1f2233', light: '#ffffff' },
    })
  } catch (e) {
    console.error('QR generation failed', e)
  }
}

onMounted(render)
watch(() => props.url, render)

async function copyLink() {
  try {
    await navigator.clipboard.writeText(props.url)
    copied.value = true
    ui.success('Link copiato negli appunti')
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    ui.error('Impossibile copiare il link')
  }
}

function downloadQr() {
  const a = document.createElement('a')
  a.href = dataUrl.value
  a.download = `snaply-qr-${props.title || 'evento'}.png`.replace(/\s+/g, '-').toLowerCase()
  a.click()
}
</script>

<template>
  <div class="card overflow-hidden p-6 sm:p-8">
    <h3 class="text-lg font-bold">Condividi con gli invitati</h3>
    <p class="mt-1 text-sm text-ink-soft">
      Inquadrano il QR o aprono il link: nessuna app, nessun account.
    </p>

    <div class="mt-6 flex flex-col items-center gap-6">
      <!-- QR -->
      <div
        class="flex shrink-0 items-center justify-center rounded-2xl border border-surface-border bg-white p-3 shadow-card transition duration-300 ease-snappy hover:shadow-card-hover"
      >
        <img v-if="dataUrl" :src="dataUrl" alt="QR code dell'evento" class="h-40 w-40" />
        <div v-else class="grid h-40 w-40 place-items-center text-ink-muted">
          <span
            class="h-6 w-6 animate-spin rounded-full border-2 border-brand-200 border-t-brand-500"
          />
        </div>
      </div>

      <!-- Link + azioni -->
      <div class="flex w-full flex-col gap-3">
        <div>
          <label class="label" for="share-url">Link pubblico</label>
          <input
            id="share-url"
            :value="url"
            readonly
            class="input text-sm"
            @focus="$event.target.select()"
          />
        </div>
        <div class="grid grid-cols-2 gap-2">
          <button
            class="btn-primary col-span-2"
            :class="copied && '!bg-success !bg-none'"
            @click="copyLink"
          >
            <transition name="err" mode="out-in">
              <span v-if="copied" key="ok">✓ Copiato!</span>
              <span v-else key="copy">Copia link</span>
            </transition>
          </button>
          <button class="btn-secondary" @click="downloadQr">Scarica QR</button>
          <a :href="url" target="_blank" rel="noopener" class="btn-ghost justify-center"
            >Apri pagina ↗</a
          >
        </div>
      </div>
    </div>
  </div>
</template>
