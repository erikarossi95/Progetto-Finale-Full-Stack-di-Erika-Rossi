// Preset di movimento centralizzati per coerenza in tutta l'app.
// Durate brevi (150–450ms) ed easing morbido "snappy" (cubic-bezier 0.16,1,0.3,1).
// Se l'utente preferisce ridurre il movimento, MotionPlugin non viene registrato
// (vedi main.js) → le direttive v-motion diventano inerti e il contenuto resta
// pienamente visibile, senza animazioni.

const EASE = [0.16, 1, 0.3, 1]

// Rivela al primo ingresso nel viewport (scroll reveal): fade + leggero translateY.
export const fadeUp = {
  initial: { opacity: 0, y: 24 },
  visibleOnce: { opacity: 1, y: 0, transition: { duration: 450, ease: EASE } },
}

// Variante senza spostamento, solo dissolvenza.
export const fadeIn = {
  initial: { opacity: 0 },
  visibleOnce: { opacity: 1, transition: { duration: 400, ease: EASE } },
}

// Reveal con stagger: ritardo incrementale ~70ms per gruppi di card.
export function stagger(index = 0, base = 0) {
  return {
    initial: { opacity: 0, y: 24 },
    visibleOnce: {
      opacity: 1,
      y: 0,
      transition: { duration: 450, delay: base + index * 70, ease: EASE },
    },
  }
}

// Sequenza coreografata per l'hero (anima al mount, non allo scroll).
export function heroStep(index = 0) {
  return {
    initial: { opacity: 0, y: 20 },
    enter: {
      opacity: 1,
      y: 0,
      transition: { duration: 500, delay: 120 + index * 110, ease: EASE },
    },
  }
}

// Pop-in per nuovi elementi (es. foto appena caricata).
export const popIn = {
  initial: { opacity: 0, scale: 0.92 },
  enter: { opacity: 1, scale: 1, transition: { duration: 320, ease: EASE } },
}
