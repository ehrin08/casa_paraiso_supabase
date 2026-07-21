<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PhCalendarCheck, PhCurrencyCircleDollar, PhHouse, PhUsersThree } from '@phosphor-icons/vue'
import MobileWorkspaceShell, { type MobileNavigationItem } from '../components/MobileWorkspaceShell.vue'
import { useAuthStore } from '../stores/auth'
import { useReceptionStore } from '../stores/reception'
import { scheduleMobilePreload } from '../lib/mobileDataCache'
import ReceptionAppointmentsView from './ReceptionAppointmentsView.vue'
import ReceptionCustomersView from './ReceptionCustomersView.vue'
import ReceptionDashboardView from './ReceptionDashboardView.vue'
import ReceptionPaymentsView from './ReceptionPaymentsView.vue'

const route = useRoute(); const auth = useAuthStore(); const router = useRouter(); const reception = useReceptionStore()
reception.configurePrefix('reception')
const items: MobileNavigationItem[] = [
  { id: 'dashboard', label: 'Today', icon: PhHouse }, { id: 'appointments', label: 'Bookings', icon: PhCalendarCheck },
  { id: 'customers', label: 'Customers', icon: PhUsersThree }, { id: 'payments', label: 'Payments', icon: PhCurrencyCircleDollar },
]
async function leave(): Promise<void> { if (await auth.signOut()) await router.replace('/sign-in') }
const tab = computed(() => ['dashboard', 'appointments', 'customers', 'payments'].includes(String(route.params.section)) ? String(route.params.section) : 'dashboard')
function select(id: string): void { void router.push(`/workspace/reception/${id}`) }
let cancelPreload=():void=>undefined
onMounted(()=>{cancelPreload=scheduleMobilePreload(()=>Promise.all([reception.loadAppointments(),reception.loadCustomers()]))})
onBeforeUnmount(()=>cancelPreload())
</script>
<template><MobileWorkspaceShell :account-label="auth.user?.name ?? 'Reception'" navigation-label="Receptionist navigation" :active-id="tab" :items="items" home-id="dashboard" :working="auth.working" :error="auth.error" @select="select" @sign-out="leave"><KeepAlive><ReceptionDashboardView v-if="tab==='dashboard'" @navigate="select"/><ReceptionAppointmentsView v-else-if="tab==='appointments'"/><ReceptionCustomersView v-else-if="tab==='customers'"/><ReceptionPaymentsView v-else/></KeepAlive></MobileWorkspaceShell></template>
