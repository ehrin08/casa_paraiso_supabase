<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { usePairingStore } from '../stores/pairing'

const router = useRouter()
const store = usePairingStore()
const { status, error, attempts, online } = storeToRefs(store)

async function retry(): Promise<void> {
  await store.bootstrap()
  if (store.status === 'paired') await router.replace('/sign-in')
}
</script>

<template>
  <main class="screen">
    <section class="panel">
      <p class="brand">Casa Paraiso</p>
      <p class="eyebrow">MOBILE APP</p>
      <h1>Starting Casa Paraiso</h1>
      <p v-if="status === 'validating'">Waking the secure Casa Paraiso server. Free hosting may need up to a minute after idle time; please keep this screen open.</p>
      <p v-else-if="!online">Connect to the internet to start Casa Paraiso.</p>
      <p v-else-if="error" class="alert" role="alert">{{ error }}</p>
      <p v-else>Preparing your secure connection.</p>
      <p v-if="status === 'unreachable' && attempts > 0" class="muted" role="status">Connection attempt {{ attempts }} did not complete. Retrying is safe.</p>
      <button class="primary" :disabled="!online || status === 'validating'" @click="retry">
        {{ status === 'validating' ? 'Starting secure connection…' : 'Retry server connection' }}
      </button>
    </section>
  </main>
</template>
