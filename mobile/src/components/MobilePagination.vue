<script setup lang="ts">
defineProps<{ current: number; last: number; loading?: boolean }>()
defineEmits<{ page: [page: number] }>()
</script>
<template><nav v-if="last>1" class="mobile-pager" aria-label="Pages"><button type="button" :disabled="loading||current<=1" @click="$emit('page',current-1)">Previous</button><span>Page {{current}} of {{last}}</span><button type="button" :disabled="loading||current>=last" @click="$emit('page',current+1)">Next</button></nav></template>
<style scoped>.mobile-pager{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:.5rem;margin-top:1rem}.mobile-pager button{min-height:48px;border:1px solid var(--casa-border);border-radius:var(--radius-control);color:var(--casa-deep-palm);background:var(--casa-paper);font-weight:800}.mobile-pager span{text-align:center;color:var(--casa-muted);font-size:.75rem}</style>
