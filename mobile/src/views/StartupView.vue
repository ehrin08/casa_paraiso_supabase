<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { usePairingStore } from '../stores/pairing'

const router = useRouter()
const store = usePairingStore()
const { status, error, online } = storeToRefs(store)

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
      <p v-if="status === 'validating'">The Render server may take up to a minute to wake. Please keep this screen open.</p>
      <p v-else-if="!online">Connect to the internet to start Casa Paraiso.</p>
      <p v-else-if="error" class="alert" role="alert">{{ error }}</p>
      <p v-else>Preparing your secure connection.</p>
      <button class="primary" :disabled="!online || status === 'validating'" @click="retry">
        {{ status === 'validating' ? 'Starting…' : 'Try again' }}
      </button>
    </section>
  </main>
</template>
