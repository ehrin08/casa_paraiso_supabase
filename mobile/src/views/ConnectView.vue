<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { isProductionBuild } from '../lib/pairing'
import { usePairingStore } from '../stores/pairing'

const router = useRouter()
const store = usePairingStore()
const { url, status, error, online } = storeToRefs(store)
const production = isProductionBuild()

async function submit() {
  await store.pair()
  if (store.status === 'paired') await router.replace('/sign-in')
}
</script>

<template>
  <main class="screen">
    <section class="panel">
      <p class="brand">Casa Paraiso</p>
      <p class="eyebrow">MOBILE APP</p>
      <template v-if="production">
        <h1>Starting Casa Paraiso</h1>
        <p>{{ status === 'validating' ? 'The demonstration server may take up to a minute to wake. Please keep this screen open.' : 'This release is securely configured for the Casa Paraiso server.' }}</p>
        <p v-if="error" class="alert" role="alert">{{ error }}</p>
        <button class="primary" :disabled="!online || status === 'validating'" @click="submit">
          {{ status === 'validating' ? 'Starting…' : 'Try again' }}
        </button>
      </template>
      <template v-else>
        <h1>Connect this phone</h1>
        <p>Paste the current Casa Paraiso link. No PIN is required.</p>
        <form class="stack" @submit.prevent="submit">
          <label>Casa Paraiso link
            <input v-model="url" class="field" type="url" inputmode="url" autocomplete="url" placeholder="https://example.trycloudflare.com" :disabled="!online || status === 'validating'" required>
          </label>
          <p v-if="error" class="alert" role="alert">{{ error }}</p>
          <button class="primary" :disabled="!online || status === 'validating'">
            {{ status === 'validating' ? 'Checking…' : 'Connect' }}
          </button>
        </form>
      </template>
    </section>
  </main>
</template>
