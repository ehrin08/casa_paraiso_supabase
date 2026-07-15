<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { PhCalendarBlank, PhCurrencyCircleDollar, PhHouse, PhUsersThree } from '@phosphor-icons/vue'
import MobileWorkspaceShell, { type MobileNavigationItem } from '../components/MobileWorkspaceShell.vue'
import { useAuthStore } from '../stores/auth'
import StaffAppointmentsView from './StaffAppointmentsView.vue'
import StaffDashboardView from './StaffDashboardView.vue'
import StaffEarningsView from './StaffEarningsView.vue'
import StaffGuestsView from './StaffGuestsView.vue'

const tab = ref<'dashboard' | 'schedule' | 'guests' | 'earnings'>('dashboard')
const auth = useAuthStore(); const router = useRouter()
const items: MobileNavigationItem[] = [
  { id: 'dashboard', label: 'Today', icon: PhHouse }, { id: 'schedule', label: 'Schedule', icon: PhCalendarBlank },
  { id: 'guests', label: 'Guests', icon: PhUsersThree }, { id: 'earnings', label: 'Earnings', icon: PhCurrencyCircleDollar },
]
async function leave(): Promise<void> { if (await auth.signOut()) await router.replace('/sign-in') }
</script>
<template><MobileWorkspaceShell :account-label="auth.user?.name ?? 'Therapist'" navigation-label="Therapist navigation" :active-id="tab" :items="items" :working="auth.working" :error="auth.error" @select="tab=$event as typeof tab" @sign-out="leave"><StaffDashboardView v-if="tab==='dashboard'" @navigate="tab=$event"/><StaffAppointmentsView v-else-if="tab==='schedule'"/><StaffGuestsView v-else-if="tab==='guests'"/><StaffEarningsView v-else/></MobileWorkspaceShell></template>
