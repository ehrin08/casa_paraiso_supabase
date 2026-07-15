<script setup lang="ts">
withDefaults(defineProps<{ as?: string; customer?: boolean }>(), { as: 'article', customer: false })
</script>
<template><component :is="as" class="mobile-card" :class="{ 'mobile-card--customer': customer }"><slot/></component></template>
<style scoped>.mobile-card{padding:1rem;border:1px solid var(--casa-border);border-radius:var(--radius-operation);background:var(--casa-paper);box-shadow:var(--shadow-card)}.mobile-card--customer{border-radius:var(--radius-customer)}</style>
