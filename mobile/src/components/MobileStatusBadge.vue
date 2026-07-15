<script setup lang="ts">
defineProps<{ status: string; label?: string }>()
</script>
<template><span class="mobile-status" :data-status="status"><slot>{{label ?? status.replace('_',' ')}}</slot></span></template>
<style scoped>.mobile-status{width:fit-content;display:inline-flex;align-items:center;min-height:24px;padding:.2rem .55rem;border-radius:999px;color:var(--casa-deep-palm);background:var(--casa-success-bg);font-size:.7rem;font-weight:800;letter-spacing:.04em;text-transform:capitalize}.mobile-status[data-status="cancelled"],.mobile-status[data-status="no_show"],.mobile-status[data-status="negative"],.mobile-status[data-status="inactive"]{color:var(--casa-error);background:var(--casa-error-bg)}.mobile-status[data-status="partial"],.mobile-status[data-status="pending"],.mobile-status[data-status="neutral"]{color:var(--casa-warning);background:var(--casa-warning-bg)}</style>
