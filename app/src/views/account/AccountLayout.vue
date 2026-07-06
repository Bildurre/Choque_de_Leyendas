<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { accountSections } from '@/account/registry'
import { useAuthStore } from '@/stores/auth'
import { useLocalesStore } from '@/stores/locales'

// Panel de usuario (doc 10): menú lateral con las secciones registradas
// (base del motor + las del juego) y la sección activa en el RouterView.
const { t } = useI18n()
const auth = useAuthStore()
const locales = useLocalesStore()
</script>

<template>
  <main class="account">
    <aside class="account__menu">
      <h1 class="account__title">{{ t('account.title') }}</h1>
      <p v-if="auth.user" class="account__who">{{ auth.user.name }}</p>
      <nav class="account__nav">
        <RouterLink
          v-for="section in accountSections"
          :key="section.key"
          class="account__link"
          :to="{ name: section.name, params: { locale: locales.current } }"
          exact-active-class="is-active"
        >
          {{ t(section.titleKey) }}
        </RouterLink>
      </nav>
    </aside>
    <section class="account__content">
      <RouterView />
    </section>
  </main>
</template>
