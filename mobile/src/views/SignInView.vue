<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { usePairingStore } from '../stores/pairing'
const auth = useAuthStore(); const pairing = usePairingStore(); const router = useRouter(); const route = useRoute(); const email = ref(''); const password = ref('')
async function submit() { if (await auth.signIn(email.value, password.value)) await router.replace(`/workspace/${auth.user?.workspace}`) }
</script>
<template><main class="screen"><section class="panel"><p class="brand">Casa Paraiso</p><p class="eyebrow">WELCOME BACK</p><h1>Sign in</h1><p>Use your verified Casa Paraiso account.</p><p v-if="route.query.notice" class="notice" role="status">{{ route.query.notice }}</p><button v-if="pairing.supportedAuth.includes('google')" class="secondary" type="button" :disabled="auth.working" @click="auth.startGoogleSignIn">Continue with Google</button><p v-if="pairing.supportedAuth.includes('google')" class="eyebrow">OR USE YOUR PASSWORD</p><form class="stack" @submit.prevent="submit"><label>Email<input v-model="email" class="field" type="email" autocomplete="email" required></label><label>Password<input v-model="password" class="field" type="password" autocomplete="current-password" required></label><p v-if="auth.error" class="alert" role="alert">{{ auth.error }}</p><button class="primary" :disabled="auth.working">{{ auth.working ? 'Signing in…' : 'Sign in' }}</button></form><RouterLink class="link" to="/connect">Change server</RouterLink></section></main></template>
