<script setup lang="ts">
import { ref } from 'vue'
import { PhFunnel, PhX } from '@phosphor-icons/vue'
withDefaults(defineProps<{ label?: string; activeCount?: number }>(), { label: 'Filters', activeCount: 0 })
const open = ref(false)
defineEmits<{ clear: [] }>()
</script>
<template><section class="mobile-filter"><button class="mobile-filter__toggle" type="button" :aria-expanded="open" @click="open=!open"><PhFunnel :size="20" aria-hidden="true"/><span>{{label}}</span><b v-if="activeCount">{{activeCount}}</b></button><div v-show="open" class="mobile-filter__body"><slot/><button v-if="activeCount" class="mobile-filter__clear" type="button" @click="$emit('clear')"><PhX :size="18" aria-hidden="true"/>Clear filters</button></div></section></template>
<style scoped>.mobile-filter{margin:1rem 0}.mobile-filter__toggle{width:100%;min-height:48px;display:flex;align-items:center;justify-content:center;gap:.5rem;border:1px solid var(--casa-border);border-radius:var(--radius-control);color:var(--casa-deep-palm);background:var(--casa-paper);font-weight:800}.mobile-filter__toggle b{min-width:1.5rem;padding:.15rem .4rem;border-radius:999px;color:#fff;background:var(--casa-palm);font-size:.7rem}.mobile-filter__body{display:grid;gap:.75rem;margin-top:.5rem;padding:1rem;border:1px solid var(--casa-border);border-radius:var(--radius-operation);background:var(--casa-paper)}.mobile-filter__clear{min-height:48px;display:flex;align-items:center;justify-content:center;gap:.4rem;border:1px solid var(--casa-border);border-radius:var(--radius-control);color:var(--casa-cacao);background:#fff;font-weight:800}</style>
