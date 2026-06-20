<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { coverUrl } from '@/utils/url'

const props = defineProps({
  event: { type: Object, required: true },
})

const cover = computed(() => coverUrl(props.event.cover_image_url))

const formattedDate = computed(() => {
  if (!props.event.event_date) return null
  return new Date(props.event.event_date).toLocaleDateString('it-IT', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
})
</script>

<template>
  <RouterLink
    :to="`/dashboard/events/${event.id}`"
    class="card-interactive group block h-full overflow-hidden"
  >
    <!-- Cover: immagine se presente, altrimenti gradiente colore. Zoom soft all'hover -->
    <div class="relative h-32 w-full overflow-hidden">
      <img
        v-if="cover"
        :src="cover"
        :alt="`Copertina di ${event.title}`"
        loading="lazy"
        decoding="async"
        class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 ease-snappy group-hover:scale-110"
      />
      <template v-else>
        <div
          class="absolute inset-0 transition-transform duration-500 ease-snappy group-hover:scale-110"
          :style="{
            background: `linear-gradient(135deg, ${event.cover_color} 0%, ${event.cover_color}bb 100%)`,
          }"
        />
        <div class="absolute inset-0 bg-dots opacity-40" />
      </template>
      <span class="badge absolute right-3 top-3 bg-white/85 text-ink shadow-sm backdrop-blur">
        {{ event.photo_count }} media
      </span>
    </div>

    <div class="p-5">
      <h3 class="truncate text-lg font-bold transition-colors group-hover:text-brand-700">
        {{ event.title }}
      </h3>
      <p v-if="formattedDate" class="mt-1 text-sm text-ink-soft">📅 {{ formattedDate }}</p>
      <p v-if="event.description" class="mt-2 line-clamp-2 text-sm text-ink-muted">
        {{ event.description }}
      </p>
      <div class="mt-4 flex items-center gap-1 text-sm font-semibold text-brand-600">
        Apri galleria
        <span class="transition-transform duration-200 group-hover:translate-x-1">→</span>
      </div>
    </div>
  </RouterLink>
</template>
