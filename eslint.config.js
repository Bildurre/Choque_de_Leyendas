import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import vueTsConfig from '@vue/eslint-config-typescript'
import vuePrettierConfig from '@vue/eslint-config-prettier'

// Config única para todo el monorepo: paquetes del motor + playground.
export default [
  {
    name: 'edc/files-to-lint',
    files: ['**/*.{ts,mts,tsx,vue}'],
  },
  {
    name: 'edc/files-to-ignore',
    ignores: [
      '**/dist/**',
      '**/node_modules/**',
      '**/coverage/**',
      '**/dev-dist/**',
      'api/**',
    ],
  },
  js.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  ...vueTsConfig(),
  vuePrettierConfig,
  {
    // Scripts de build en Node (p. ej. el prerender de la app): globals de
    // Node en vez de las del navegador.
    name: 'edc/node-scripts',
    files: ['**/scripts/**/*.mjs'],
    languageOptions: {
      globals: {
        process: 'readonly',
        console: 'readonly',
        fetch: 'readonly',
        URL: 'readonly',
        setTimeout: 'readonly',
        document: 'readonly', // dentro de page.evaluate (contexto del navegador)
      },
    },
  },
  {
    rules: {
      'vue/multi-word-component-names': 'off',
      // Con props tipadas de script setup, `label?: string` ya declara el default
      // (undefined); exigir `default:` aparte es ruido.
      'vue/require-default-prop': 'off',
      'vue/no-v-html': 'warn',
      '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
      '@typescript-eslint/no-explicit-any': 'warn',
    },
  },
]
