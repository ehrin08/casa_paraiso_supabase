<script setup lang="ts">
import { onMounted } from 'vue'
import { formatAppointmentDate, formatPeso } from '../lib/appointments'
import { useReceptionStore } from '../stores/reception'

const emit = defineEmits<{ navigate: [tab: 'appointments' | 'customers' | 'payments'] }>()
const store = useReceptionStore()
onMounted(() => store.loadDashboard())
</script>

<template>
  <section class="ops-page" aria-labelledby="desk-title">
    <header><p class="eyebrow">Front desk</p><h1 id="desk-title">Today at Casa Paraiso</h1><p>Keep arrivals, bookings, and payments moving smoothly.</p></header>
    <p v-if="store.error" class="alert" role="alert">{{ store.error }}</p>
    <div v-if="store.loading" class="loading" role="status">Loading front desk…</div>
    <template v-else-if="store.dashboard">
      <div class="metric-grid">
        <button @click="emit('navigate','appointments')"><strong>{{ store.dashboard.summary.today }}</strong><span>Visits today</span></button>
        <button @click="emit('navigate','appointments')"><strong>{{ store.dashboard.summary.upcoming }}</strong><span>Upcoming</span></button>
        <button @click="emit('navigate','customers')"><strong>{{ store.dashboard.summary.customers }}</strong><span>Customers</span></button>
        <button @click="emit('navigate','payments')"><strong>{{ formatPeso(store.dashboard.summary.payments_today) }}</strong><span>Paid today</span></button>
      </div>
      <section class="today" aria-labelledby="today-title"><div class="section-title"><h2 id="today-title">Today’s schedule</h2><button @click="store.loadDashboard()">Refresh</button></div>
        <div v-if="store.dashboard.today_appointments.length" class="ops-list"><article v-for="appointment in store.dashboard.today_appointments" :key="appointment.id" class="ops-card"><div class="time">{{ formatAppointmentDate(appointment.starts_at) }}</div><strong>{{ appointment.customer?.name }}</strong><span>{{ appointment.service?.name }} · {{ appointment.therapist?.name }}</span><small :data-status="appointment.status">{{ appointment.status.replace('_',' ') }}</small></article></div>
        <div v-else class="empty-state"><h3>No visits today</h3><p>New bookings will appear here.</p></div>
      </section>
    </template>
  </section>
</template>

<style scoped>
.ops-page{width:min(100%,54rem);margin:auto;padding:max(1.25rem,env(safe-area-inset-top)) 1rem calc(6rem + env(safe-area-inset-bottom))}.ops-page h1,.ops-page h2{font-family:Georgia,serif;color:#334736}.ops-page header p{margin:.25rem 0}.loading{padding:2rem;text-align:center;color:#67675f}.metric-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;margin:1.25rem 0}.metric-grid button{min-height:96px;display:grid;gap:.25rem;padding:1rem;border:1px solid #dcd2c2;border-radius:1rem;background:#fffcf7;color:#232620;text-align:left}.metric-grid strong{font-size:1.35rem;color:#334736}.metric-grid span{font-size:.8rem;color:#67675f}.section-title{display:flex;align-items:center;justify-content:space-between;gap:1rem}.section-title button{min-height:44px;border:1px solid #dcd2c2;border-radius:.7rem;background:#fffcf7;color:#334736;font-weight:700}.ops-list{display:grid;gap:.65rem}.ops-card{display:grid;gap:.25rem;padding:1rem;border:1px solid #dcd2c2;border-radius:1rem;background:#fffcf7}.ops-card .time{color:#7a3e14;font-weight:800}.ops-card span{color:#67675f}.ops-card small{width:max-content;padding:.2rem .5rem;border-radius:999px;background:#e9f2e8;color:#334736;text-transform:capitalize}.empty-state{padding:1.5rem;border:1px dashed #bfb3a2;border-radius:1rem;text-align:center;background:#fffcf7}.empty-state h3{margin:0;color:#334736}
</style>
