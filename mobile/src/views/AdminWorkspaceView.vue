<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PhChartLineUp, PhDotsThreeCircle, PhHouse, PhSlidersHorizontal, PhWrench } from '@phosphor-icons/vue'
import MobileWorkspaceShell, { type MobileNavigationItem } from '../components/MobileWorkspaceShell.vue'
import MobileModalSheet from '../components/MobileModalSheet.vue'
import { useAuthStore } from '../stores/auth'
import { useAdminStore } from '../stores/admin'
import { useAdminInsightsStore } from '../stores/adminInsights'
import { useReceptionStore } from '../stores/reception'
import { scheduleMobilePreload } from '../lib/mobileDataCache'
import AdminControlView from './AdminControlView.vue'
import AdminDashboardView from './AdminDashboardView.vue'
import AdminInsightsView from './AdminInsightsView.vue'
import AdminManagementView from './AdminManagementView.vue'
import AdminOperationsView from './AdminOperationsView.vue'

const route = useRoute(); const moreOpen = ref(false)
const auth = useAuthStore(); const router = useRouter()
const admin=useAdminStore();const insights=useAdminInsightsStore();const operations=useReceptionStore();operations.configurePrefix('admin')
const items: MobileNavigationItem[] = [
  { id: 'dashboard', label: 'Today', icon: PhHouse }, { id: 'operations', label: 'Ops', icon: PhSlidersHorizontal },
  { id: 'manage', label: 'Manage', icon: PhWrench }, { id: 'insights', label: 'Insights', icon: PhChartLineUp },
]
async function leave(): Promise<void> { if (await auth.signOut()) await router.replace('/sign-in') }
const tab = computed(() => ['dashboard', 'operations', 'manage', 'insights', 'control'].includes(String(route.params.section)) ? String(route.params.section) : 'dashboard')
function select(id: string): void { if (id === 'more') { moreOpen.value = true; return }; void router.push(`/workspace/admin/${id}`) }
function openDashboardTarget(target: 'operations' | 'staff' | 'services'): void { void router.push({ path: `/workspace/admin/${target === 'operations' ? 'operations' : 'manage'}`, query: target === 'operations' ? {} : { manage: target } }) }
let cancelPreload=():void=>undefined
onMounted(()=>{cancelPreload=scheduleMobilePreload(async()=>{await Promise.all([operations.loadAppointments(),admin.loadStaff()]);await insights.loadFeedback()})})
onBeforeUnmount(()=>cancelPreload())
</script>
<template><MobileWorkspaceShell :account-label="auth.user?.name ?? 'Administrator'" navigation-label="Administrator navigation" :active-id="tab" :items="items" home-id="dashboard" :working="auth.working" :error="auth.error" @select="select" @sign-out="leave"><KeepAlive><AdminDashboardView v-if="tab==='dashboard'" @navigate="openDashboardTarget"/><AdminOperationsView v-else-if="tab==='operations'"/><AdminManagementView v-else-if="tab==='manage'"/><AdminInsightsView v-else-if="tab==='insights'"/><AdminControlView v-else/></KeepAlive></MobileWorkspaceShell><button class="admin-more-fab" type="button" aria-label="More administrator options" @click="moreOpen=true"><PhDotsThreeCircle :size="25" weight="bold" aria-hidden="true"/></button><MobileModalSheet :open="moreOpen" title="More options" eyebrow="Administrator" @close="moreOpen=false"><button class="more-option" type="button" @click="moreOpen=false; select('control')">Control centre</button><button class="more-option more-option--danger" type="button" @click="moreOpen=false; leave()">Sign out</button></MobileModalSheet></template>
<style scoped>.admin-more-fab{position:fixed;z-index:21;right:.65rem;bottom:calc(var(--dock-height) + var(--safe-bottom) + .65rem);width:48px;height:48px;border:1px solid var(--casa-border);border-radius:999px;color:var(--casa-deep-palm);background:var(--casa-paper);box-shadow:var(--shadow-card)}.more-option{min-height:52px;border:1px solid var(--casa-border);border-radius:var(--radius-control);color:var(--casa-deep-palm);background:#fff;font-weight:800}.more-option--danger{color:var(--casa-error);background:var(--casa-error-bg)}</style>
