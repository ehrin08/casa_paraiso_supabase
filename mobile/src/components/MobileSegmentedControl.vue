<script setup lang="ts">
export interface MobileSegmentItem { id: string; label: string }
defineProps<{ label: string; activeId: string; items: MobileSegmentItem[] }>()
defineEmits<{ select: [id: string] }>()
</script>
<template>
  <div class="mobile-segments mobile-scroll-region" role="tablist" :aria-label="label">
    <button v-for="item in items" :key="item.id" type="button" role="tab" :aria-selected="activeId === item.id" :class="{ active: activeId === item.id }" @click="$emit('select', item.id)">{{ item.label }}</button>
  </div>
</template>
<style scoped>
.mobile-segments { position: sticky; z-index: 12; top: calc(var(--app-bar-height) + var(--safe-top) + .5rem); width: min(calc(100% - 2rem), 34rem); display: flex; gap: .25rem; margin: .75rem auto 0; padding: .25rem; border: 1px solid var(--casa-border); border-radius: .9rem; background: rgb(255 252 247 / 96%); box-shadow: var(--shadow-card); backdrop-filter: blur(12px); }
.mobile-segments button { min-width: max-content; min-height: 48px; flex: 1 1 0; border: 0; border-radius: .7rem; padding: .5rem .65rem; color: var(--casa-muted); background: transparent; font-size: .8rem; font-weight: 800; }
.mobile-segments button.active { color: var(--casa-deep-palm); background: var(--casa-success-bg); }
</style>
