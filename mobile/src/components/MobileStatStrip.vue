<script setup lang="ts">
export interface MobileStatItem { label: string; value: string | number }
defineProps<{ label: string; items: MobileStatItem[] }>()
</script>
<template><div class="mobile-stat-strip" :aria-label="label"><div v-for="item in items" :key="item.label"><strong>{{ item.value }}</strong><span>{{ item.label }}</span></div></div></template>
<style scoped>.mobile-stat-strip{display:grid;grid-template-columns:repeat(var(--stat-count,3),minmax(0,1fr));overflow:hidden;border:1px solid var(--casa-border);border-radius:var(--radius-operation);background:var(--casa-paper);box-shadow:var(--shadow-card)}.mobile-stat-strip>div{min-width:0;display:grid;gap:.2rem;padding:.8rem .45rem;text-align:center}.mobile-stat-strip>div+div{border-left:1px solid var(--casa-border)}strong{overflow:hidden;text-overflow:ellipsis;color:var(--casa-deep-palm);font-size:1.15rem}span{color:var(--casa-muted);font-size:.7rem;font-weight:700}</style>
