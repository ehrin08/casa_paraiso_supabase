<script setup lang="ts">
import { PhCheckCircle, PhClock, PhWarningCircle, PhXCircle } from '@phosphor-icons/vue'
defineProps<{ status: string; label?: string }>()
</script>
<template>
  <span class="mobile-status" :data-status="status">
    <PhCheckCircle v-if="['confirmed', 'completed', 'paid', 'active', 'positive'].includes(status)" :size="14" weight="bold" aria-hidden="true" />
    <PhWarningCircle v-else-if="['pending', 'partial', 'neutral'].includes(status)" :size="14" weight="bold" aria-hidden="true" />
    <PhXCircle v-else-if="['cancelled', 'no_show', 'inactive', 'negative'].includes(status)" :size="14" weight="bold" aria-hidden="true" />
    <PhClock v-else :size="14" weight="bold" aria-hidden="true" />
    <span><slot>{{ label ?? status.replace('_', ' ') }}</slot></span>
  </span>
</template>
<style scoped>
.mobile-status { width: fit-content; display: inline-flex; align-items: center; gap: .3rem; min-height: 24px; padding: .2rem .55rem; border-radius: 999px; color: var(--casa-deep-palm); background: var(--casa-success-bg); font-size: .72rem; font-weight: 800; letter-spacing: .04em; text-transform: capitalize; }
.mobile-status[data-status="cancelled"], .mobile-status[data-status="no_show"], .mobile-status[data-status="negative"], .mobile-status[data-status="inactive"] { color: var(--casa-error); background: var(--casa-error-bg); }
.mobile-status[data-status="partial"], .mobile-status[data-status="pending"], .mobile-status[data-status="neutral"] { color: var(--casa-warning); background: var(--casa-warning-bg); }
</style>
