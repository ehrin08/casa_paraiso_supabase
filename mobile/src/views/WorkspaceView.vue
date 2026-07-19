<script setup lang="ts">
import { computed, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PhCalendarBlank, PhChatsCircle, PhUserCircle } from '@phosphor-icons/vue'
import MobileWorkspaceShell, { type MobileNavigationItem } from '../components/MobileWorkspaceShell.vue'
import { useAuthStore } from '../stores/auth'
import AdminWorkspaceView from './AdminWorkspaceView.vue'
import CustomerAppointmentsView from './CustomerAppointmentsView.vue'
import CustomerFeedbackView from './CustomerFeedbackView.vue'
import CustomerProfileView from './CustomerProfileView.vue'
import ReceptionWorkspaceView from './ReceptionWorkspaceView.vue'
import StaffWorkspaceView from './StaffWorkspaceView.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const title = computed(() => ({ admin: 'Admin workspace', reception: 'Reception workspace', staff: 'Therapist workspace', customer: 'My appointments' }[auth.user?.workspace ?? 'customer']))
const customerItems: MobileNavigationItem[] = [
  { id: 'appointments', label: 'Appointments', icon: PhCalendarBlank },
  { id: 'feedback', label: 'Feedback', icon: PhChatsCircle },
  { id: 'profile', label: 'Profile', icon: PhUserCircle },
]

watchEffect(() => {
  if (!auth.user) void router.replace('/sign-in')
  else if (router.currentRoute.value.params.workspace !== auth.user.workspace) void router.replace(`/workspace/${auth.user.workspace}`)
})

async function leave(): Promise<void> { if (await auth.signOut()) await router.replace('/sign-in') }
const customerTab = computed(() => ['appointments', 'feedback', 'profile'].includes(String(route.params.section)) ? String(route.params.section) : 'appointments')
const feedbackAppointmentId = computed(() => route.query.appointment ? Number(route.query.appointment) : null)
function workspacePath(section: string, query: Record<string, string | undefined> = {}): void { void router.push({ path: `/workspace/customer/${section}`, query }) }
function openFeedback(appointmentId: number): void { workspacePath('feedback', { appointment: String(appointmentId) }) }
function selectCustomerTab(id: string): void { workspacePath(id) }
</script>

<template>
  <MobileWorkspaceShell
    v-if="auth.user?.workspace === 'customer'"
    :account-label="auth.user.name"
    navigation-label="Customer navigation"
    :active-id="customerTab"
    :items="customerItems"
    home-id="appointments"
    :working="auth.working"
    :error="auth.error"
    @select="selectCustomerTab"
    @sign-out="leave"
  >
    <CustomerAppointmentsView v-if="customerTab === 'appointments'" :service-id="route.query.service ? Number(route.query.service) : null" @feedback="openFeedback" />
    <CustomerFeedbackView v-else-if="customerTab === 'feedback'" :appointment-id="feedbackAppointmentId" />
    <CustomerProfileView v-else />
  </MobileWorkspaceShell>
  <ReceptionWorkspaceView v-else-if="auth.user?.workspace === 'reception'" />
  <StaffWorkspaceView v-else-if="auth.user?.workspace === 'staff'" />
  <AdminWorkspaceView v-else-if="auth.user?.workspace === 'admin'" />
  <main v-else class="screen">
    <section class="panel">
      <p class="eyebrow">{{ auth.user?.role }}</p><h1>{{ title }}</h1>
      <p>Signed in as {{ auth.user?.name }}. Your role-aware mobile workspace is ready.</p>
      <p v-if="auth.error" class="alert" role="alert">{{ auth.error }}</p>
      <button class="primary" :disabled="auth.working" @click="leave">{{ auth.working ? 'Signing out…' : 'Sign out' }}</button>
    </section>
  </main>
</template>
