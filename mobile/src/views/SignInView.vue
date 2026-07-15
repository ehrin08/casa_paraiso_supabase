<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
const auth = useAuthStore(); const router = useRouter(); const email = ref(''); const password = ref('')
async function submit() { if (await auth.signIn(email.value, password.value)) await router.replace(`/workspace/${auth.user?.workspace}`) }
</script>
<template><main class="screen"><section class="panel"><p class="brand">Casa Paraiso</p><p class="eyebrow">WELCOME BACK</p><h1>Sign in</h1><p>Use your verified Casa Paraiso email and password.</p><form class="stack" @submit.prevent="submit"><label>Email<input v-model="email" class="field" type="email" autocomplete="email" required></label><label>Password<input v-model="password" class="field" type="password" autocomplete="current-password" required></label><p v-if="auth.error" class="alert" role="alert">{{ auth.error }}</p><button class="primary" :disabled="auth.working">{{ auth.working ? 'Signing in…' : 'Sign in' }}</button></form><RouterLink class="link" to="/connect">Change server</RouterLink></section></main></template>
