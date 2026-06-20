<script setup>
import { computed, inject } from 'vue'
import { useRoute } from 'vue-router'
import AppNavbar from '@/components/AppNavbar.vue'
import ToastContainer from '@/components/ToastContainer.vue'

const route = useRoute()
const reduceMotion = inject('reduceMotion', false)

// La navbar app non compare sulle pagine pubbliche degli invitati né sul 404,
// che hanno un'intestazione propria/minimale.
const hideNavbar = computed(() => ['public-event', 'not-found'].includes(route.name))

// Se l'utente preferisce ridurre il movimento, niente transizione di pagina.
const pageTransition = computed(() => (reduceMotion ? '' : 'page'))
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <AppNavbar v-if="!hideNavbar" />
    <main class="flex-1">
      <!-- Il componente di rotta è sempre avvolto in un singolo <div> keyed:
           così la <transition> ha un root unico e non resta mai bloccata
           (i fragment multi-root rompevano la transizione). -->
      <router-view v-slot="{ Component, route: r }">
        <transition :name="pageTransition" mode="out-in">
          <div :key="r.path">
            <component :is="Component" />
          </div>
        </transition>
      </router-view>
    </main>
    <ToastContainer />
  </div>
</template>
