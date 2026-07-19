<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { PhArrowClockwise } from '@phosphor-icons/vue'
import MobileConfirmDialog from '../components/MobileConfirmDialog.vue'
import MobileSuccessSheet from '../components/MobileSuccessSheet.vue'
import MobilePagination from '../components/MobilePagination.vue'
import MobileStatStrip from '../components/MobileStatStrip.vue'
import MobileSkeleton from '../components/MobileSkeleton.vue'
import { useInitialLoad } from '../composables/useInitialLoad'
import { appointmentStatusLabel, formatAppointmentDate, formatPeso } from '../lib/appointments'
import type { MobileAppointment } from '../lib/api'
import { useCustomerAppointmentsStore } from '../stores/customerAppointments'
import CustomerBookingView from './CustomerBookingView.vue'

const store = useCustomerAppointmentsStore()
const props = defineProps<{ serviceId?: number | null }>()
const emit = defineEmits<{ feedback: [appointmentId: number] }>()
const openId = ref<number | null>(null)
const bookingOpen = ref(false)
const cancelTarget = ref<MobileAppointment | null>(null)
const successMessage = ref('')
const { initialLoading, loadInitial } = useInitialLoad()

onMounted(() => void loadInitial(() => store.load()))
watch(() => props.serviceId, serviceId => { if (serviceId) bookingOpen.value = true }, { immediate: true })

function confirmCancel(appointment: MobileAppointment): void {
  cancelTarget.value = appointment
}

async function cancelAppointment(): Promise<void> {
  if (!cancelTarget.value) return
  const appointment = cancelTarget.value
  cancelTarget.value = null
  await store.cancel(appointment)
}

async function booked(message: string): Promise<void> {
  bookingOpen.value = false
  successMessage.value = message
  await store.load(1)
}
</script>

<template>
  <section class="customer-workspace" aria-labelledby="appointments-title">
    <header class="customer-heading">
      <div>
        <p class="eyebrow">Your spa visits</p>
        <h1 id="appointments-title">My appointments</h1>
        <p class="intro">Your time and therapist are reserved as soon as booking succeeds.</p>
      </div>
      <button class="icon-button" aria-label="Refresh appointments" :disabled="store.loading" @click="store.load(store.meta.current_page)"><PhArrowClockwise :size="23" weight="bold" aria-hidden="true" /></button>
    </header>

    <button class="primary book-button" @click="bookingOpen = true">Book an appointment</button>

    <MobileStatStrip class="summary-strip" label="Appointment summary" :items="[{label:'Upcoming',value:store.summary.upcoming},{label:'Completed',value:store.summary.completed},{label:'Cancelled',value:store.summary.cancelled}]" />

    <label class="filter-label">
      <span>Show appointments</span>
      <select v-model="store.status" class="field" @change="store.load(1)">
        <option value="">All appointments</option>
        <option value="confirmed">Confirmed</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
        <option value="no_show">No-show</option>
      </select>
    </label>

    <p v-if="store.error" class="alert" role="alert">{{ store.error }}</p>
    <p v-if="store.notice" class="notice" role="status">{{ store.notice }}</p>
    <MobileSkeleton v-if="initialLoading" variant="list" label="Loading your appointments" />

    <div v-else-if="store.appointments.length" class="appointment-list">
      <article v-for="appointment in store.appointments" :key="appointment.id" class="appointment-card">
        <button class="appointment-summary" :aria-expanded="openId === appointment.id" @click="openId = openId === appointment.id ? null : appointment.id">
          <span class="date-tile"><b>{{ new Date(appointment.starts_at ?? '').toLocaleDateString('en-PH', { timeZone: 'Asia/Manila', day: '2-digit' }) }}</b><small>{{ new Date(appointment.starts_at ?? '').toLocaleDateString('en-PH', { timeZone: 'Asia/Manila', month: 'short' }) }}</small></span>
          <span class="appointment-main">
            <span class="status-badge" :data-status="appointment.status">{{ appointmentStatusLabel(appointment.status) }}</span>
            <strong>{{ appointment.service?.name ?? 'Service unavailable' }}</strong>
            <small>{{ formatAppointmentDate(appointment.starts_at) }}</small>
          </span>
          <span aria-hidden="true">{{ openId === appointment.id ? '−' : '+' }}</span>
        </button>

        <div v-if="openId === appointment.id" class="appointment-details">
          <dl>
            <div><dt>Booking</dt><dd>{{ appointment.appointment_number }}</dd></div>
            <div><dt>Therapist</dt><dd>{{ appointment.therapist?.name ?? 'To be assigned' }}</dd></div>
            <div><dt>Ends</dt><dd>{{ formatAppointmentDate(appointment.ends_at) }}</dd></div>
            <div><dt>Expected total</dt><dd>{{ formatPeso(appointment.expected_amount) }}</dd></div>
          </dl>
          <p v-if="appointment.addons.length"><strong>Paid add-ons:</strong> {{ appointment.addons.map(addon => addon.name).join(', ') }}</p>
          <p v-if="appointment.voucher"><strong>Reward:</strong> {{ appointment.voucher.name }}</p>
          <p v-if="appointment.customer_notes"><strong>Your note:</strong> {{ appointment.customer_notes }}</p>
          <button v-if="appointment.can_cancel" class="danger-button" :disabled="store.cancellingId === appointment.id" @click="confirmCancel(appointment)">
            {{ store.cancellingId === appointment.id ? 'Cancelling…' : 'Cancel appointment' }}
          </button>
          <button v-else-if="appointment.can_submit_feedback" class="feedback-button" @click="emit('feedback', appointment.id)">Leave feedback</button>
        </div>
      </article>
    </div>

    <div v-else class="empty-state">
      <h2>No appointments found</h2>
      <p>Try another status, or reserve your next Casa Paraiso visit now.</p>
      <button class="primary" @click="bookingOpen = true">Reserve your spot</button>
    </div>

    <MobilePagination :current="store.meta.current_page" :last="store.meta.last_page" :loading="store.loading" @page="store.load" />
    <CustomerBookingView v-if="bookingOpen" :service-id="props.serviceId" @close="bookingOpen = false" @booked="booked" />
    <MobileSuccessSheet :open="!!successMessage" title="Appointment confirmed" :message="successMessage" action-label="View my appointments" @close="successMessage=''" @action="successMessage=''" />
    <MobileConfirmDialog :open="!!cancelTarget" title="Cancel appointment?" :message="cancelTarget ? `Cancel ${cancelTarget.appointment_number}? This will release the reserved time.` : ''" confirm-label="Cancel appointment" destructive @cancel="cancelTarget=null" @confirm="cancelAppointment" />
  </section>
</template>

<style scoped>
.customer-workspace { width: min(100%, 42rem); margin: 0 auto; padding: max(1.25rem, env(safe-area-inset-top)) 1rem calc(6rem + env(safe-area-inset-bottom)); }
.customer-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.customer-heading h1 { font-family: Georgia, serif; color: #334736; }
.book-button { width: 100%; margin-top: 1rem; }
.intro { margin: 0; }
.icon-button { width: 48px; min-width: 48px; height: 48px; border-radius: 999px; border: 1px solid #dcd2c2; background: #fffcf7; color: #334736; font-size: 1.5rem; }
.summary-strip { margin: 1.25rem 0; }
.filter-label { margin-bottom: 1rem; }.filter-label span { font-size: .85rem; }.filter-label .field { width: 100%; }
.notice { padding: .75rem; border-radius: .75rem; background: #e9f2e8; color: #334736; }.loading { padding: 2rem; text-align: center; color: #67675f; }
.appointment-list { display: grid; gap: .75rem; }.appointment-card { overflow: hidden; border: 1px solid #dcd2c2; border-radius: 1rem; background: #fffcf7; box-shadow: 0 6px 18px rgb(35 38 32 / 6%); }
.appointment-summary { width: 100%; min-height: 76px; display: grid; grid-template-columns: 50px 1fr 24px; align-items: center; gap: .8rem; border: 0; padding: .75rem; background: transparent; color: #232620; text-align: left; }
.date-tile { display: grid; place-items: center; border-radius: .75rem; padding: .4rem; background: #f3ebdd; color: #7a3e14; }.date-tile b { font-size: 1.2rem; }.date-tile small { text-transform: uppercase; }
.appointment-main { min-width: 0; display: grid; gap: .25rem; }.appointment-main strong,.appointment-main small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.appointment-main small { color: #67675f; }
.status-badge { width: fit-content; border-radius: 999px; padding: .18rem .5rem; background: #e9f2e8; color: #334736; font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; }.status-badge[data-status="cancelled"],.status-badge[data-status="no_show"] { background: #f3e4df; color: #7a3e14; }.status-badge[data-status="completed"] { background: #eee8da; color: #655021; }
.appointment-details { border-top: 1px solid #dcd2c2; padding: 1rem; }.appointment-details dl { display: grid; gap: .65rem; margin: 0; }.appointment-details dl div { display: flex; justify-content: space-between; gap: 1rem; }.appointment-details dt { color: #67675f; }.appointment-details dd { margin: 0; text-align: right; font-weight: 700; }.appointment-details p { font-size: .9rem; }
.danger-button,.feedback-button { min-height: 48px; border-radius: .75rem; font: inherit; font-weight: 700; width: 100%; }.danger-button { border: 1px solid #a04a3c; background: #fff7f5; color: #8c2f26; }.feedback-button { border: 1px solid #4f6a4e; background: #e9f2e8; color: #334736; }.danger-button:disabled { opacity: .5; }
.empty-state { padding: 2rem 1rem; border: 1px dashed #bfb3a2; border-radius: 1rem; text-align: center; background: #fffcf7; }.empty-state h2 { margin: 0; font-family: Georgia, serif; color: #334736; }
@media (min-width: 640px) { .customer-workspace { padding-inline: 1.5rem; } }
</style>
