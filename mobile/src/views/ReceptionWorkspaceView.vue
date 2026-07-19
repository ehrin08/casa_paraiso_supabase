<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { PhCalendarCheck, PhCurrencyCircleDollar, PhHouse, PhUsersThree } from '@phosphor-icons/vue'
import MobileWorkspaceShell, { type MobileNavigationItem } from '../components/MobileWorkspaceShell.vue'
import { useAuthStore } from '../stores/auth'
import { useReceptionStore } from '../stores/reception'
import ReceptionAppointmentsView from './ReceptionAppointmentsView.vue'
import ReceptionCustomersView from './ReceptionCustomersView.vue'
import ReceptionDashboardView from './ReceptionDashboardView.vue'
import ReceptionPaymentsView from './ReceptionPaymentsView.vue'

const tab = ref<'dashboard' | 'appointments' | 'customers' | 'payments'>('dashboard')
const auth = useAuthStore(); const router = useRouter(); const reception = useReceptionStore()
reception.configurePrefix('reception')
const items: MobileNavigationItem[] = [
  { id: 'dashboard', label: 'Today', icon: PhHouse }, { id: 'appointments', label: 'Bookings', icon: PhCalendarCheck },
  { id: 'customers', label: 'Customers', icon: PhUsersThree }, { id: 'payments', label: 'Payments', icon: PhCurrencyCircleDollar },
]
async function leave(): Promise<void> { if (await auth.signOut()) await router.replace('/sign-in') }
</script>
<template><MobileWorkspaceShell :account-label="auth.user?.name ?? 'Reception'" navigation-label="Receptionist navigation" :active-id="tab" :items="items" home-id="dashboard" :working="auth.working" :error="auth.error" @select="tab=$event as typeof tab" @sign-out="leave"><ReceptionDashboardView v-if="tab==='dashboard'" @navigate="tab=$event"/><ReceptionAppointmentsView v-else-if="tab==='appointments'"/><ReceptionCustomersView v-else-if="tab==='customers'"/><ReceptionPaymentsView v-else/></MobileWorkspaceShell></template>
