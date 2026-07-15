<script setup lang="ts">
import { onMounted } from 'vue'
import { formatAppointmentDate, formatPeso } from '../lib/appointments'
import { useStaffStore } from '../stores/staff'

const emit = defineEmits<{ navigate: [tab: 'schedule' | 'guests' | 'earnings'] }>()
const store = useStaffStore()
onMounted(() => store.loadDashboard())
</script>

<template>
  <section class="page" aria-labelledby="staff-today-title">
    <header><p class="eyebrow">My treatment day</p><h1 id="staff-today-title">Today at Casa Paraiso</h1><p v-if="store.dashboard">{{ store.dashboard.profile.specialization || 'Spa therapist' }}</p></header>
    <p v-if="store.error" class="alert" role="alert">{{ store.error }}</p><div v-if="store.loading" class="loading">Loading your day…</div>
    <template v-else-if="store.dashboard">
      <div class="metrics"><button @click="emit('navigate','schedule')"><strong>{{store.dashboard.summary.assigned_today}}</strong><span>Assigned today</span></button><button @click="emit('navigate','schedule')"><strong>{{store.dashboard.summary.upcoming}}</strong><span>Upcoming</span></button><button @click="emit('navigate','guests')"><strong>{{store.dashboard.summary.feedback}}</strong><span>Guest reviews</span></button><button @click="emit('navigate','earnings')"><strong>{{formatPeso(store.dashboard.commissions.pending)}}</strong><span>Pending earnings</span></button></div>
      <section><div class="title"><h2>Today’s agenda</h2><button @click="store.loadDashboard()">Refresh</button></div><div v-if="store.dashboard.today_appointments.length" class="list"><article v-for="item in store.dashboard.today_appointments" :key="item.id" class="card"><b>{{formatAppointmentDate(item.starts_at)}}</b><strong>{{item.customer?.name}}</strong><span>{{item.service?.name}}</span><small>{{item.status.replace('_',' ')}}</small></article></div><div v-else class="empty"><h3>No assigned visits today</h3><p>Your confirmed treatments will appear here.</p></div></section>
    </template>
  </section>
</template>

<style scoped>
.page{width:min(100%,54rem);margin:auto;padding:max(1.25rem,env(safe-area-inset-top)) 1rem calc(6rem + env(safe-area-inset-bottom))}.page h1,.page h2,.page h3{font-family:Georgia,serif;color:#334736}.page header p{margin:.25rem 0}.loading{padding:2rem;text-align:center}.metrics{display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;margin:1.25rem 0}.metrics button{min-height:96px;display:grid;gap:.25rem;padding:1rem;border:1px solid #dcd2c2;border-radius:1rem;background:#fffcf7;text-align:left}.metrics strong{font-size:1.25rem;color:#334736}.metrics span,.card span{color:#67675f}.title{display:flex;align-items:center;justify-content:space-between}.title button{min-height:44px;border:1px solid #dcd2c2;border-radius:.7rem;background:#fff;color:#334736;font-weight:800}.list{display:grid;gap:.65rem}.card{display:grid;gap:.25rem;padding:1rem;border:1px solid #dcd2c2;border-radius:1rem;background:#fffcf7}.card b{color:#7a3e14}.card small{width:max-content;padding:.2rem .5rem;border-radius:999px;background:#e9f2e8;text-transform:capitalize}.empty{padding:1.5rem;border:1px dashed #bfb3a2;border-radius:1rem;text-align:center;background:#fffcf7}.empty h3{margin:0}
</style>
