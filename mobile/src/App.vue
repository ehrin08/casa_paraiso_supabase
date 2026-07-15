<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { usePairingStore } from './stores/pairing'

const store = usePairingStore()
const { url, code, instanceId, pairedAt, status, error, online } = storeToRefs(store)
</script>

<template>
  <main class="min-h-dvh bg-stone-50 px-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-[max(2rem,env(safe-area-inset-top))] text-stone-900">
    <section class="mx-auto max-w-md">
      <p class="text-sm font-semibold tracking-[0.2em] text-violet-800">CASA PARAISO</p>
      <h1 class="mt-3 text-3xl font-bold tracking-tight text-violet-950">Connect this phone</h1>
      <p class="mt-3 text-base leading-7 text-stone-600">Pair with the local Casa Paraiso demonstration server. The app UI stays on this device; only the API connection uses the temporary tunnel.</p>

      <div class="mt-6 rounded-2xl border border-stone-200 bg-white p-4 text-sm shadow-sm" :class="online ? 'text-emerald-700' : 'text-amber-800'">
        {{ online ? 'Phone network available' : 'Phone is offline — reconnect, then retry.' }}
      </div>

      <form v-if="status !== 'paired'" class="mt-6 space-y-5" @submit.prevent="store.pair">
        <label class="block">
          <span class="mb-2 block text-sm font-semibold">Tunnel address</span>
          <input v-model="url" autocomplete="url" inputmode="url" placeholder="https://quiet-lotus.trycloudflare.com" class="field" :disabled="status === 'validating' || status === 'verifying'" />
        </label>
        <label class="block">
          <span class="mb-2 block text-sm font-semibold">One-time pairing code</span>
          <input v-model="code" autocomplete="one-time-code" inputmode="numeric" maxlength="8" placeholder="12345678" class="field tracking-[0.35em]" :disabled="status === 'validating' || status === 'verifying'" />
        </label>
        <p v-if="error" role="alert" class="rounded-xl bg-rose-50 p-4 text-sm leading-6 text-rose-800">{{ error }}</p>
        <button class="primary-button" type="submit" :disabled="!online || status === 'validating' || status === 'verifying'">
          {{ status === 'verifying' ? 'Verifying code…' : status === 'validating' ? 'Checking server…' : 'Pair this phone' }}
        </button>
      </form>

      <section v-else class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
        <p class="text-sm font-semibold text-emerald-800">Phone paired</p>
        <p class="mt-2 break-all text-sm leading-6 text-emerald-950">{{ url }}</p>
        <p class="mt-2 text-xs text-emerald-800">Instance {{ instanceId }} · paired {{ pairedAt }}</p>
        <p class="mt-5 text-sm leading-6 text-emerald-900">Authentication and spa workspaces are the next mobile milestone.</p>
        <button class="secondary-button mt-5" type="button" @click="store.revalidate">Check connection again</button>
      </section>

      <p class="mt-8 text-xs leading-5 text-stone-500">A rotating tunnel needs re-pairing. Codes expire after five minutes and are never saved on this phone.</p>
    </section>
  </main>
</template>
