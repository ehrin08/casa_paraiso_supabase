<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { usePairingStore } from '../stores/pairing'
const auth = useAuthStore(); const pairing = usePairingStore(); const router = useRouter(); const route = useRoute(); const email = ref(''); const password = ref('')
async function submit() { if (await auth.signIn(email.value, password.value)) await router.replace(auth.user?.workspace === 'customer' ? { path: '/workspace/customer/appointments', query: route.query.service ? { service: String(route.query.service) } : {} } : `/workspace/${auth.user?.workspace}`) }
</script>
<template><main class="screen"><section class="panel"><p class="brand">Casa Paraiso</p><p class="eyebrow">YOUR CALM DOORWAY</p><h1>Welcome to Casa Paraiso</h1><p>Sign in with your email and password or continue securely with Google.</p><p v-if="route.query.notice" class="notice" role="status">{{ route.query.notice }}</p><form class="stack" @submit.prevent="submit"><label>Email<input v-model="email" class="field" type="email" autocomplete="email" required></label><label>Password<input v-model="password" class="field" type="password" autocomplete="current-password" required></label><p v-if="auth.error" class="alert" role="alert">{{ auth.error }}</p><button class="primary" :disabled="auth.working">{{ auth.working ? 'Signing in…' : 'Sign in' }}</button></form><template v-if="pairing.supportedAuth.includes('google')"><div class="sign-in-divider" aria-hidden="true"><span></span><b>or</b><span></span></div><button class="secondary sign-in-google" type="button" :disabled="auth.working" @click="auth.startGoogleSignIn">Continue with Google</button></template></section></main></template>
