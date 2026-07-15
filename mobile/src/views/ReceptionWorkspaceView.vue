<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useReceptionStore } from '../stores/reception'
import ReceptionAppointmentsView from './ReceptionAppointmentsView.vue'
import ReceptionCustomersView from './ReceptionCustomersView.vue'
import ReceptionDashboardView from './ReceptionDashboardView.vue'
import ReceptionPaymentsView from './ReceptionPaymentsView.vue'

const tab=ref<'dashboard'|'appointments'|'customers'|'payments'>('dashboard');const auth=useAuthStore();const router=useRouter()
const reception=useReceptionStore();reception.configurePrefix('reception')
async function leave():Promise<void>{if(await auth.signOut())await router.replace('/sign-in')}
</script>
<template><main class="reception-shell"><div class="account-bar"><span>{{auth.user?.name}}</span><button :disabled="auth.working" @click="leave">{{auth.working?'Leaving…':'Sign out'}}</button></div><p v-if="auth.error" class="floating-alert alert">{{auth.error}}</p><ReceptionDashboardView v-if="tab==='dashboard'" @navigate="tab=$event"/><ReceptionAppointmentsView v-else-if="tab==='appointments'"/><ReceptionCustomersView v-else-if="tab==='customers'"/><ReceptionPaymentsView v-else/><nav class="ops-dock" aria-label="Receptionist navigation"><button :class="{active:tab==='dashboard'}" :aria-current="tab==='dashboard'?'page':undefined" @click="tab='dashboard'"><span>⌂</span><small>Today</small></button><button :class="{active:tab==='appointments'}" :aria-current="tab==='appointments'?'page':undefined" @click="tab='appointments'"><span>◷</span><small>Bookings</small></button><button :class="{active:tab==='customers'}" :aria-current="tab==='customers'?'page':undefined" @click="tab='customers'"><span>♙</span><small>Customers</small></button><button :class="{active:tab==='payments'}" :aria-current="tab==='payments'?'page':undefined" @click="tab='payments'"><span>₱</span><small>Payments</small></button></nav></main></template>
<style scoped>
.reception-shell{min-height:100dvh}.account-bar{position:fixed;z-index:12;top:max(.5rem,env(safe-area-inset-top));right:.6rem;display:flex;align-items:center;gap:.5rem;padding:.3rem .35rem .3rem .65rem;border:1px solid #dcd2c2;border-radius:999px;background:rgb(255 252 247 / 94%);box-shadow:0 4px 14px rgb(35 38 32 / 9%)}.account-bar span{max-width:9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.75rem;font-weight:700}.account-bar button{min-height:36px;border:0;border-radius:999px;background:#f3ebdd;color:#7a3e14;font-weight:800}.floating-alert{position:fixed;z-index:20;top:4rem;right:.75rem;left:.75rem}.ops-dock{position:fixed;z-index:15;right:0;bottom:0;left:0;display:grid;grid-template-columns:repeat(4,1fr);padding:.4rem .35rem max(.4rem,env(safe-area-inset-bottom));border-top:1px solid #dcd2c2;background:rgb(255 252 247 / 96%);box-shadow:0 -8px 24px rgb(35 38 32 / 8%);backdrop-filter:blur(12px)}.ops-dock button{min-height:50px;display:grid;place-items:center;gap:.1rem;border:0;border-radius:.7rem;background:transparent;color:#67675f}.ops-dock button.active{background:#e9f2e8;color:#334736}.ops-dock span{font-size:1.15rem}.ops-dock small{font-size:.65rem;font-weight:800}
</style>
