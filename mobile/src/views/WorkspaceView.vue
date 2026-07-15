<script setup lang="ts">
import { computed, watchEffect } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
const auth = useAuthStore(); const router = useRouter()
const title = computed(() => ({ admin: 'Admin workspace', reception: 'Reception workspace', staff: 'Therapist workspace', customer: 'My appointments' }[auth.user?.workspace ?? 'customer']))
watchEffect(() => { if (!auth.user) void router.replace('/sign-in'); else if (router.currentRoute.value.params.workspace !== auth.user.workspace) void router.replace(`/workspace/${auth.user.workspace}`) })
async function leave() { if (await auth.signOut()) await router.replace('/sign-in') }
</script>
<template><main class="screen"><section class="panel"><p class="eyebrow">{{ auth.user?.role }}</p><h1>{{ title }}</h1><p>Signed in as {{ auth.user?.name }}. Your role-aware mobile workspace is ready for the next API modules.</p><p v-if="auth.error" class="alert" role="alert">{{ auth.error }}</p><button class="primary" :disabled="auth.working" @click="leave">{{ auth.working ? 'Signing out…' : 'Sign out' }}</button></section></main></template>
