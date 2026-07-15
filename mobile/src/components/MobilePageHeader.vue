<script setup lang="ts">
defineProps<{ eyebrow?: string; title: string; description?: string; customer?: boolean }>()
</script>
<template><header class="mobile-heading" :class="{ 'mobile-heading--customer': customer }"><p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p><h1>{{ title }}</h1><p v-if="description">{{ description }}</p><slot /></header></template>
<style scoped>.mobile-heading{margin-bottom:1.25rem}.mobile-heading h1{font-family:var(--font-body);font-size:clamp(1.6rem,6vw,2rem);font-weight:800;line-height:1.1}.mobile-heading--customer h1{font-family:var(--font-display);font-size:clamp(2rem,8vw,2.6rem)}.mobile-heading p:last-child{max-width:42rem;margin-bottom:0}</style>
