// Directive v-focus-trap: rende un modale accessibile da tastiera.
// - All'apertura sposta il focus sul primo elemento focusabile (o sul contenitore).
// - Intrappola Tab / Shift+Tab dentro il contenitore.
// - Esc chiama la funzione passata come valore della direttiva (chiusura).
// - Alla chiusura ripristina il focus sull'elemento che aveva il focus prima.
//
// Uso: <div v-focus-trap="closeFn" role="dialog" aria-modal="true"> … </div>

const SELECTOR = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

function focusable(container) {
  return Array.from(container.querySelectorAll(SELECTOR)).filter(
    (el) => el.offsetParent !== null || el === document.activeElement
  )
}

export const focusTrap = {
  mounted(el, binding) {
    el.__prevFocus = document.activeElement

    el.__onKey = (e) => {
      if (e.key === 'Escape') {
        if (typeof binding.value === 'function') binding.value()
        return
      }
      if (e.key !== 'Tab') return
      const items = focusable(el)
      if (!items.length) {
        e.preventDefault()
        el.focus()
        return
      }
      const first = items[0]
      const last = items[items.length - 1]
      const active = document.activeElement
      if (e.shiftKey && (active === first || !el.contains(active))) {
        e.preventDefault()
        last.focus()
      } else if (!e.shiftKey && active === last) {
        e.preventDefault()
        first.focus()
      }
    }

    document.addEventListener('keydown', el.__onKey, true)

    // Il contenitore deve poter ricevere il focus come fallback.
    if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '-1')

    // Focus iniziale dopo il primo paint (evita salti di scroll).
    requestAnimationFrame(() => {
      const items = focusable(el)
      ;(items[0] || el).focus?.({ preventScroll: true })
    })
  },

  unmounted(el) {
    document.removeEventListener('keydown', el.__onKey, true)
    // Ripristina il focus al trigger, se ancora nel DOM.
    if (el.__prevFocus && document.contains(el.__prevFocus)) {
      el.__prevFocus.focus?.({ preventScroll: true })
    }
    el.__prevFocus = null
    el.__onKey = null
  },
}
