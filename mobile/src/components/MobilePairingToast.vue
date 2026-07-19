<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { PhCheckCircle, PhCircleNotch, PhWarningCircle } from '@phosphor-icons/vue'
import { useAuthStore } from '../stores/auth'
import { usePairingStore } from '../stores/pairing'

const pairing = usePairingStore()
const auth = useAuthStore()
const dismissed = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null
const visible = computed(() => !auth.user && !dismissed.value && ['validating', 'paired', 'unreachable'].includes(pairing.status))
const message = computed(() => pairing.status === 'validating' ? 'Connecting to Casa Paraiso…' : pairing.status === 'paired' ? 'Ready to book' : pairing.error || 'Casa Paraiso is temporarily unavailable.')
const retryLabel = computed(() => pairing.status === 'unreachable' ? 'Retry' : '')
function retry(): void { dismissed.value = false; void pairing.bootstrap() }
function close(): void { dismissed.value = true }
watch(() => pairing.status, status => {
  if (timer) clearTimeout(timer)
  dismissed.value = false
  if (status === 'paired') timer = setTimeout(close, 2400)
})
onBeforeUnmount(() => { if (timer) clearTimeout(timer) })
</script>

<template>
  <Transition name="pairing-toast">
    <section v-if="visible" class="pairing-toast" :class="`pairing-toast--${pairing.status}`" :role="pairing.status === 'unreachable' ? 'alert' : 'status'" aria-live="polite">
      <PhCircleNotch v-if="pairing.status === 'validating'" class="pairing-toast__spinner" :size="20" weight="bold" aria-hidden="true"/>
      <PhCheckCircle v-else-if="pairing.status === 'paired'" :size="20" weight="fill" aria-hidden="true"/>
      <PhWarningCircle v-else :size="20" weight="fill" aria-hidden="true"/>
      <span>{{ message }}</span>
      <button v-if="retryLabel" type="button" @click="retry">{{ retryLabel }}</button>
      <button v-else type="button" aria-label="Dismiss connection status" @click="close">×</button>
    </section>
  </Transition>
</template>

<style scoped>
.pairing-toast{position:fixed;z-index:60;right:1rem;bottom:max(1rem,var(--safe-bottom));left:1rem;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.65rem;max-width:34rem;margin:auto;padding:.8rem 1rem;border:1px solid var(--casa-border);border-radius:var(--radius-operation);color:var(--casa-deep-palm);background:var(--casa-paper);box-shadow:var(--shadow-float);font-size:.86rem;font-weight:700}.pairing-toast--unreachable{border-color:var(--casa-error);color:var(--casa-error);background:var(--casa-error-bg)}.pairing-toast--paired{border-color:var(--casa-success);color:var(--casa-success);background:var(--casa-success-bg)}.pairing-toast button{min-height:36px;border:0;border-radius:.5rem;padding:.3rem .55rem;color:inherit;background:rgb(255 255 255 / 55%);font-weight:800}.pairing-toast__spinner{animation:spin .8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}.pairing-toast-enter-active,.pairing-toast-leave-active{transition:opacity var(--motion-fast) ease,transform var(--motion-standard) ease}.pairing-toast-enter-from,.pairing-toast-leave-to{opacity:0;transform:translateY(.5rem)}@media(prefers-reduced-motion:reduce){.pairing-toast__spinner{animation:none}}
</style>
