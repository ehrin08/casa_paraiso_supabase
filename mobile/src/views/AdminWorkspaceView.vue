<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { PhChartLineUp, PhGearSix, PhHouse, PhSlidersHorizontal, PhWrench } from '@phosphor-icons/vue'
import MobileWorkspaceShell, { type MobileNavigationItem } from '../components/MobileWorkspaceShell.vue'
import { useAuthStore } from '../stores/auth'
import AdminControlView from './AdminControlView.vue'
import AdminDashboardView from './AdminDashboardView.vue'
import AdminInsightsView from './AdminInsightsView.vue'
import AdminManagementView from './AdminManagementView.vue'
import AdminOperationsView from './AdminOperationsView.vue'

const tab = ref<'dashboard' | 'operations' | 'manage' | 'insights' | 'control'>('dashboard')
const auth = useAuthStore(); const router = useRouter()
const items: MobileNavigationItem[] = [
  { id: 'dashboard', label: 'Today', icon: PhHouse }, { id: 'operations', label: 'Ops', icon: PhSlidersHorizontal },
  { id: 'manage', label: 'Manage', icon: PhWrench }, { id: 'insights', label: 'Insights', icon: PhChartLineUp },
  { id: 'control', label: 'Control', icon: PhGearSix },
]
async function leave(): Promise<void> { if (await auth.signOut()) await router.replace('/sign-in') }
function openDashboardTarget(target: 'operations' | 'staff' | 'services'): void { tab.value = target === 'operations' ? 'operations' : 'manage' }
</script>
<template><MobileWorkspaceShell :account-label="auth.user?.name ?? 'Administrator'" navigation-label="Administrator navigation" :active-id="tab" :items="items" home-id="dashboard" :working="auth.working" :error="auth.error" @select="tab=$event as typeof tab" @sign-out="leave"><AdminDashboardView v-if="tab==='dashboard'" @navigate="openDashboardTarget"/><AdminOperationsView v-else-if="tab==='operations'"/><AdminManagementView v-else-if="tab==='manage'"/><AdminInsightsView v-else-if="tab==='insights'"/><AdminControlView v-else/></MobileWorkspaceShell></template>
