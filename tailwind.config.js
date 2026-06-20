/** @type {import('tailwindcss').Config} */
// Snaply — configurazione Tailwind con brand identity centralizzata.
// Tutta l'identità visiva vive qui: cambiando un token, cambia tutta la SPA.

export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        // --- Brand primario: viola Snaply (fresco, giovane, energico) ---
        brand: {
          50:  '#f3f1fd',
          100: '#e9e5fb',
          200: '#d5cef8',
          300: '#b8acf2',
          400: '#9580ea',
          500: '#6c5ce7', // colore brand di riferimento
          600: '#5a45d9',
          700: '#4c37c0',
          800: '#3f2f9c',
          900: '#362b7c',
        },

        // --- Accent secondario: corallo caldo (CTA, evidenziazioni) ---
        accent: {
          50:  '#fff1f0',
          100: '#ffe0de',
          200: '#ffc6c3',
          300: '#ffa19c',
          400: '#ff7675', // accent di riferimento
          500: '#f85050',
          600: '#e43535',
          700: '#c02727',
          800: '#9e2424',
          900: '#832424',
        },

        // --- Colori di stato (toast, feedback, validazione) ---
        success: {
          light: '#d1fae5',
          DEFAULT: '#10b981',
          dark: '#047857',
        },
        error: {
          light: '#fee2e2',
          DEFAULT: '#ef4444',
          dark: '#b91c1c',
        },
        warning: {
          light: '#fef3c7',
          DEFAULT: '#f59e0b',
          dark: '#b45309',
        },
        info: {
          light: '#dbeafe',
          DEFAULT: '#3b82f6',
          dark: '#1d4ed8',
        },

        // --- Neutri (testo, bordi, sfondi) ---
        ink: {
          DEFAULT: '#1f2233', // testo principale
          soft: '#4b4f63',    // testo secondario
          muted: '#8a8fa3',   // placeholder, hint
        },
        surface: {
          DEFAULT: '#ffffff', // card, pannelli
          muted: '#f6f7fb',   // sfondo pagina
          border: '#e7e8f0',  // bordi sottili
        },
      },

      fontFamily: {
        // Display per titoli/brand: geometrico e amichevole.
        // Body per il testo: leggibile e neutro.
        display: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
      },

      borderRadius: {
        // Angoli morbidi = look moderno e accogliente.
        xl: '1rem',
        '2xl': '1.25rem',
        '3xl': '1.75rem',
      },

      boxShadow: {
        // Ombre soft con tinta brand, niente nero piatto.
        card: '0 4px 24px -8px rgba(108, 92, 231, 0.18)',
        'card-hover': '0 8px 32px -8px rgba(108, 92, 231, 0.28)',
        focus: '0 0 0 3px rgba(108, 92, 231, 0.35)',
      },

      backgroundImage: {
        // Gradiente brand per hero, header evento, pulsanti principali.
        'brand-gradient': 'linear-gradient(135deg, #6c5ce7 0%, #9580ea 100%)',
        'brand-warm': 'linear-gradient(135deg, #6c5ce7 0%, #ff7675 100%)',
      },

      transitionTimingFunction: {
        snappy: 'cubic-bezier(0.16, 1, 0.3, 1)',
      },
    },
  },
  plugins: [
    // Consigliato per uno styling pulito dei form:
    // require('@tailwindcss/forms'),
  ],
}
