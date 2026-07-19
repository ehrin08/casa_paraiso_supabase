<script setup lang="ts">
import { PhCheckCircle } from '@phosphor-icons/vue'
import MobileModalSheet from './MobileModalSheet.vue'
defineProps<{ open: boolean; title: string; message: string; actionLabel?: string }>()
defineEmits<{ close: []; action: [] }>()
</script>

<template>
  <MobileModalSheet :open="open" :title="title" eyebrow="All set" @close="$emit('close')">
    <section class="success-sheet" role="status"><PhCheckCircle :size="46" weight="fill" aria-hidden="true"/><p>{{ message }}</p><button type="button" class="primary" @click="$emit('action')">{{ actionLabel ?? 'Done' }}</button></section>
  </MobileModalSheet>
</template>

<style scoped>.success-sheet{display:grid;justify-items:center;gap:1rem;padding:1.5rem 0;text-align:center}.success-sheet svg{color:var(--casa-success)}.success-sheet p{max-width:28rem;margin:0}.success-sheet button{width:min(100%,24rem)}</style>
