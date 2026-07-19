<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'dashboard' | 'list' | 'form' | 'calendar'
  label?: string
}>(), {
  variant: 'list',
  label: 'Loading content',
})
</script>

<template>
  <section class="mobile-skeleton" :class="`mobile-skeleton--${variant}`" role="status" :aria-label="label">
    <span class="sr-only">{{ label }}</span>
    <div class="mobile-skeleton__content" aria-hidden="true">
      <template v-if="variant === 'dashboard'">
        <div class="mobile-skeleton__metrics"><i v-for="index in 4" :key="index" class="mobile-skeleton__metric" /></div>
        <i class="mobile-skeleton__line mobile-skeleton__line--heading" />
        <i v-for="index in 3" :key="`item-${index}`" class="mobile-skeleton__card" />
      </template>
      <template v-else-if="variant === 'calendar'">
        <i class="mobile-skeleton__line mobile-skeleton__line--heading" />
        <div class="mobile-skeleton__calendar"><i v-for="index in 35" :key="index" /></div>
        <i class="mobile-skeleton__card" />
      </template>
      <template v-else-if="variant === 'form'">
        <i class="mobile-skeleton__line mobile-skeleton__line--heading" />
        <label v-for="index in 5" :key="index"><i /><b /></label>
      </template>
      <template v-else>
        <i class="mobile-skeleton__line mobile-skeleton__line--heading" />
        <i v-for="index in 5" :key="index" class="mobile-skeleton__card" />
      </template>
    </div>
  </section>
</template>
