// Helper di formattazione condivisi (date relative, avatar testuali).

/** Tempo relativo in italiano: "adesso", "5 minuti fa", "2 ore fa", "ieri"… */
export function timeAgo(dateStr) {
  if (!dateStr) return ''
  // Il backend restituisce "YYYY-MM-DD HH:MM:SS" (ora locale del server).
  const d = new Date(String(dateStr).replace(' ', 'T'))
  if (isNaN(d)) return ''
  const sec = Math.max(0, (Date.now() - d.getTime()) / 1000)
  if (sec < 45) return 'adesso'
  if (sec < 3600) {
    const m = Math.floor(sec / 60)
    return `${m} ${m === 1 ? 'minuto' : 'minuti'} fa`
  }
  if (sec < 86400) {
    const h = Math.floor(sec / 3600)
    return `${h} ${h === 1 ? 'ora' : 'ore'} fa`
  }
  const days = Math.floor(sec / 86400)
  if (days === 1) return 'ieri'
  if (days < 7) return `${days} giorni fa`
  return d.toLocaleDateString('it-IT', { day: 'numeric', month: 'long' })
}

/** Data + ora estesa per il lightbox. */
export function fullDateTime(dateStr) {
  if (!dateStr) return ''
  const d = new Date(String(dateStr).replace(' ', 'T'))
  if (isNaN(d)) return ''
  return d.toLocaleString('it-IT', {
    day: 'numeric',
    month: 'long',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/** Iniziale maiuscola di un nome (o '?' se anonimo). */
export function initialOf(name) {
  return (name || '?').trim().charAt(0).toUpperCase() || '?'
}

// Palette avatar: solo tinte del brand (viola) e accent (corallo). Niente blu.
const AVATAR_PALETTE = ['#6c5ce7', '#5a45d9', '#9580ea', '#ff7675', '#f85050', '#b8acf2']

/** Colore avatar deterministico dal nome (coerente tra i render). */
export function avatarColor(name) {
  const s = (name || 'anon').split('').reduce((a, c) => a + c.charCodeAt(0), 0)
  return AVATAR_PALETTE[s % AVATAR_PALETTE.length]
}
