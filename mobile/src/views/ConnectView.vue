<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { usePairingStore } from '../stores/pairing'
const router = useRouter(); const store = usePairingStore(); const { url, code, status, error, online } = storeToRefs(store)
async function submit() { await store.pair(); if (store.status === 'paired') await router.replace('/sign-in') }
</script>
<template><main class="screen"><section class="panel"><p class="brand">Casa Paraiso</p><p class="eyebrow">MOBILE APP</p><h1>Connect this phone</h1><p>Pair with the current Casa Paraiso demonstration server.</p><form class="stack" @submit.prevent="submit"><label>Tunnel address<input v-model="url" class="field" placeholder="https://quiet-lotus.trycloudflare.com" :disabled="!online || status === 'validating' || status === 'verifying'"></label><label>One-time pairing code<input v-model="code" class="field" inputmode="numeric" maxlength="8" :disabled="!online || status === 'validating' || status === 'verifying'"></label><p v-if="error" class="alert" role="alert">{{ error }}</p><button class="primary" :disabled="!online || status === 'validating' || status === 'verifying'">{{ status === 'verifying' ? 'Verifying…' : 'Pair this phone' }}</button></form></section></main></template>
