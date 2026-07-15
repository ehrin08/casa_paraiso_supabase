<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue'
import { useRouter } from 'vue-router'
import CustomerAppointmentsView from './CustomerAppointmentsView.vue'
import CustomerFeedbackView from './CustomerFeedbackView.vue'
import CustomerProfileView from './CustomerProfileView.vue'
import ReceptionWorkspaceView from './ReceptionWorkspaceView.vue'
import { useAuthStore } from '../stores/auth'
const auth = useAuthStore(); const router = useRouter()
const customerTab = ref<'appointments' | 'feedback' | 'profile'>('appointments')
const feedbackAppointmentId = ref<number | null>(null)
const title = computed(() => ({ admin: 'Admin workspace', reception: 'Reception workspace', staff: 'Therapist workspace', customer: 'My appointments' }[auth.user?.workspace ?? 'customer']))
watchEffect(() => { if (!auth.user) void router.replace('/sign-in'); else if (router.currentRoute.value.params.workspace !== auth.user.workspace) void router.replace(`/workspace/${auth.user.workspace}`) })
async function leave() { if (await auth.signOut()) await router.replace('/sign-in') }
function openFeedback(appointmentId: number) { feedbackAppointmentId.value = appointmentId; customerTab.value = 'feedback' }
</script>
<template>
  <main v-if="auth.user?.workspace === 'customer'" class="workspace-screen">
    <p v-if="auth.error" class="floating-alert alert" role="alert">{{ auth.error }}</p>
    <CustomerAppointmentsView v-if="customerTab === 'appointments'" @feedback="openFeedback" />
    <CustomerFeedbackView v-else-if="customerTab === 'feedback'" :appointment-id="feedbackAppointmentId" />
    <CustomerProfileView v-else />
    <nav class="customer-dock" aria-label="Customer navigation">
      <button :class="{ active: customerTab === 'appointments' }" :aria-current="customerTab === 'appointments' ? 'page' : undefined" aria-label="Appointments" @click="customerTab = 'appointments'"><span aria-hidden="true">◷</span><small>Appointments</small></button>
      <button :class="{ active: customerTab === 'feedback' }" :aria-current="customerTab === 'feedback' ? 'page' : undefined" aria-label="Feedback" @click="feedbackAppointmentId = null; customerTab = 'feedback'"><span aria-hidden="true">☆</span><small>Feedback</small></button>
      <button :class="{ active: customerTab === 'profile' }" :aria-current="customerTab === 'profile' ? 'page' : undefined" aria-label="Profile" @click="customerTab = 'profile'"><span aria-hidden="true">○</span><small>Profile</small></button>
    </nav>
  </main>
  <ReceptionWorkspaceView v-else-if="auth.user?.workspace === 'reception'" />
  <main v-else class="screen">
    <section class="panel">
      <p class="eyebrow">{{ auth.user?.role }}</p><h1>{{ title }}</h1>
      <p>Signed in as {{ auth.user?.name }}. Your role-aware mobile workspace is ready for the next API modules.</p>
      <p v-if="auth.error" class="alert" role="alert">{{ auth.error }}</p>
      <button class="primary" :disabled="auth.working" @click="leave">{{ auth.working ? 'Signing out…' : 'Sign out' }}</button>
    </section>
  </main>
</template>

<style scoped>
.workspace-screen { min-height: 100dvh; }
.floating-alert { position: fixed; z-index: 20; top: max(.75rem, env(safe-area-inset-top)); right: .75rem; left: .75rem; margin: 0 auto; max-width: 40rem; box-shadow: 0 8px 24px rgb(35 38 32 / 14%); }
.customer-dock { position: fixed; z-index: 10; right: 0; bottom: 0; left: 0; display: grid; grid-template-columns: repeat(3, 1fr); padding: .45rem .5rem max(.45rem, env(safe-area-inset-bottom)); border-top: 1px solid #dcd2c2; background: rgb(255 252 247 / 96%); box-shadow: 0 -8px 24px rgb(35 38 32 / 8%); backdrop-filter: blur(12px); }
.customer-dock a,.customer-dock button { min-height: 48px; display: grid; place-items: center; gap: .1rem; border: 0; border-radius: .75rem; background: transparent; color: #67675f; font: inherit; text-decoration: none; }.customer-dock .active { background: #e9f2e8; color: #334736; }.customer-dock span { font-size: 1.25rem; }.customer-dock small { font-size: .7rem; font-weight: 700; }.customer-dock button:disabled { opacity: .6; }
</style>
