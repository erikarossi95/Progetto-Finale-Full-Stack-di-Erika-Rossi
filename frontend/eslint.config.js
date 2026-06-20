import js from '@eslint/js'
import vue from 'eslint-plugin-vue'
import prettier from 'eslint-config-prettier'
import globals from 'globals'

export default [
  { ignores: ['dist/', 'node_modules/'] },
  js.configs.recommended,
  ...vue.configs['flat/recommended'],
  // Disattiva le regole stilistiche in conflitto con Prettier.
  prettier,
  {
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: { ...globals.browser },
    },
    rules: {
      // I nomi dei componenti view/single-file possono essere a parola singola.
      'vue/multi-word-component-names': 'off',
      // Formattazione e ordine attributi: li gestisce Prettier / scelta di stile.
      'vue/first-attribute-linebreak': 'off',
      'vue/attributes-order': 'off',
      'vue/singleline-html-element-content-newline': 'off',
      'vue/max-attributes-per-line': 'off',
      'vue/html-self-closing': 'off',
    },
  },
  // I file di config Node usano i global di Node.
  {
    files: ['*.config.js', 'postcss.config.js', 'tailwind.config.js'],
    languageOptions: { globals: { ...globals.node } },
  },
]
