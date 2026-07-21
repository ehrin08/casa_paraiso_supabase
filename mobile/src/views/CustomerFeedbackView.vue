<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { PhArrowClockwise } from '@phosphor-icons/vue'
import { formatAppointmentDate } from '../lib/appointments'
import type { EligibleFeedbackAppointment } from '../lib/api'
import { useCustomerFeedbackStore } from '../stores/customerFeedback'
import MobileSkeleton from '../components/MobileSkeleton.vue'
import { useInitialLoad } from '../composables/useInitialLoad'

const props = defineProps<{ appointmentId?: number | null }>()
const store = useCustomerFeedbackStore()
const selected = ref<EligibleFeedbackAppointment | null>(null)
const rating = ref(5)
const comment = ref('')
const { initialLoading, loadInitial } = useInitialLoad(() => store.hasLoaded())

onMounted(() => void loadInitial(async () => {
  await store.load()
  if (props.appointmentId) open(store.eligibleAppointments.find(item => item.id === props.appointmentId) ?? null)
}))

function open(appointment: EligibleFeedbackAppointment | null): void {
  if (!appointment) return
  selected.value = appointment
  rating.value = 5
  comment.value = ''
  store.error = ''
}

async function submit(): Promise<void> {
  if (!selected.value) return
  if (await store.submit(selected.value.id, rating.value, comment.value)) selected.value = null
}
</script>

<template>
  <section class="customer-page" aria-labelledby="feedback-title">
    <header class="page-heading">
      <div><p class="eyebrow">Share your experience</p><h1 id="feedback-title">Feedback</h1><p>Your comments help Casa Paraiso care for every visit.</p></div>
      <button class="icon-button" aria-label="Refresh feedback" :disabled="store.loading || store.refreshing" @click="store.load(store.meta.current_page, true)"><PhArrowClockwise :size="23" weight="bold" aria-hidden="true" /></button>
    </header>

    <div class="summary-strip" aria-label="Feedback summary">
      <div><strong>{{ store.summary.awaiting_feedback }}</strong><span>Awaiting</span></div>
      <div><strong>{{ store.summary.submitted }}</strong><span>Submitted</span></div>
    </div>

    <p v-if="store.error" class="alert" role="alert">{{ store.error }}</p>
    <p v-if="store.notice" class="notice" role="status">{{ store.notice }}</p>
    <MobileSkeleton v-if="initialLoading" variant="list" label="Loading your feedback" />

    <template v-else>
      <section v-if="store.eligibleAppointments.length" class="section-block" aria-labelledby="awaiting-title">
        <h2 id="awaiting-title">Visits awaiting feedback</h2>
        <article v-for="appointment in store.eligibleAppointments" :key="appointment.id" class="feedback-card awaiting-card">
          <div><strong>{{ appointment.service?.name ?? 'Spa visit' }}</strong><span>{{ formatAppointmentDate(appointment.completed_at) }}</span><small>{{ appointment.appointment_number }} · {{ appointment.therapist?.name ?? 'Casa Paraiso therapist' }}</small></div>
          <button @click="open(appointment)">Rate visit</button>
        </article>
      </section>

      <section class="section-block" aria-labelledby="history-title">
        <h2 id="history-title">My feedback history</h2>
        <div v-if="store.feedback.length" class="feedback-list">
          <article v-for="item in store.feedback" :key="item.id" class="feedback-card history-card">
            <div class="card-top"><strong>{{ item.service?.name ?? 'Spa visit' }}</strong><span :aria-label="`${item.rating} out of 5 stars`">{{ '★'.repeat(item.rating) }}<i>{{ '★'.repeat(5 - item.rating) }}</i></span></div>
            <p v-if="item.comment">“{{ item.comment }}”</p><p v-else class="muted">No written comment.</p>
            <small>{{ formatAppointmentDate(item.submitted_at) }} · {{ item.appointment?.appointment_number }}</small>
          </article>
        </div>
        <div v-else class="empty-state"><h3>No feedback yet</h3><p>Completed visits ready for a rating will appear here.</p></div>
      </section>

      <nav v-if="store.meta.last_page > 1" class="pager" aria-label="Feedback pages">
        <button :disabled="store.loading || store.meta.current_page <= 1" @click="store.load(store.meta.current_page - 1)">Previous</button>
        <span>Page {{ store.meta.current_page }} of {{ store.meta.last_page }}</span>
        <button :disabled="store.loading || store.meta.current_page >= store.meta.last_page" @click="store.load(store.meta.current_page + 1)">Next</button>
      </nav>
    </template>

    <section v-if="selected" v-mobile-modal="() => selected = null" class="feedback-sheet" role="dialog" aria-modal="true" aria-labelledby="rate-title">
      <header><div><p class="eyebrow">{{ selected.service?.name }}</p><h2 id="rate-title">How was your visit?</h2></div><button aria-label="Close feedback" @click="selected = null">×</button></header>
      <form @submit.prevent="submit">
        <fieldset><legend>Your rating</legend><div class="rating-row">
          <label v-for="star in 5" :key="star"><input v-model="rating" type="radio" name="rating" :value="star"><span aria-hidden="true">★</span><b class="sr-only">{{ star }} stars</b></label>
        </div></fieldset>
        <label><span>Your comments (optional)</span><textarea v-model="comment" maxlength="5000" rows="5" placeholder="Tell us what felt good and what we can improve."></textarea></label>
        <p v-if="store.error" class="alert" role="alert">{{ store.error }}</p>
        <button class="primary" :disabled="store.submitting">{{ store.submitting ? 'Submitting…' : 'Submit feedback' }}</button>
      </form>
    </section>
  </section>
</template>

<style scoped>
.customer-page { width: min(100%,42rem); margin: 0 auto; padding: max(1.25rem,env(safe-area-inset-top)) 1rem calc(6rem + env(safe-area-inset-bottom)); }.page-heading { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start }.page-heading h1,.section-block h2,.feedback-sheet h2 { font-family:var(--font-display); color:var(--casa-deep-palm) }.page-heading p { margin:.25rem 0 0 }.icon-button { width:48px; min-width:48px; height:48px; border:1px solid var(--casa-border); border-radius:999px; background:var(--casa-paper); color:var(--casa-deep-palm); font-size:1.5rem }.summary-strip { display:grid; grid-template-columns:repeat(2,1fr); margin:1.25rem 0; border:1px solid var(--casa-border); border-radius:var(--radius-operation); overflow:hidden; background:var(--casa-paper) }.summary-strip div { display:grid; gap:.2rem; padding:.8rem; text-align:center }.summary-strip div+div { border-left:1px solid var(--casa-border) }.summary-strip strong { color:var(--casa-deep-palm); font-size:1.25rem }.summary-strip span,.feedback-card small,.feedback-card>div>span,.muted { color:var(--casa-muted) }.notice { padding:.75rem; border-radius:var(--radius-control); background:var(--casa-success-bg); color:var(--casa-success) }.loading { padding:2rem; text-align:center; color:var(--casa-muted) }.section-block { margin-top:1.5rem }.section-block h2 { font-size:1.3rem }.feedback-list { display:grid; gap:.75rem }.feedback-card { border:1px solid var(--casa-border); border-radius:var(--radius-operation); background:var(--casa-paper); padding:1rem; box-shadow:var(--shadow-card) }.awaiting-card { display:grid; gap:.75rem }.awaiting-card>div { display:grid; gap:.25rem }.awaiting-card button,.pager button { min-height:48px; border:1px solid var(--casa-palm); border-radius:var(--radius-control); background:var(--casa-success-bg); color:var(--casa-deep-palm); font:inherit; font-weight:800 }.history-card p { margin:.75rem 0 }.card-top { display:flex; justify-content:space-between; gap:1rem }.card-top span { color:var(--casa-brass); letter-spacing:.08em; white-space:nowrap }.card-top i { color:var(--casa-border); font-style:normal }.empty-state { padding:1.5rem; border:1px dashed var(--casa-border-strong); border-radius:var(--radius-operation); text-align:center; background:var(--casa-paper) }.empty-state h3 { margin:0; color:var(--casa-deep-palm) }.pager { display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:.5rem; margin-top:1rem }.pager span { font-size:.8rem; text-align:center; color:var(--casa-muted) }.pager button:disabled { opacity:.5 }.feedback-sheet { position:fixed; z-index:30; inset:0; overflow:auto; padding:max(1rem,env(safe-area-inset-top)) 1rem max(1rem,env(safe-area-inset-bottom)); background:var(--casa-background) }.feedback-sheet>header { display:flex; justify-content:space-between; align-items:flex-start; max-width:38rem; margin:0 auto 1rem }.feedback-sheet>header button { width:48px; height:48px; border:1px solid var(--casa-border); border-radius:999px; background:var(--casa-paper); font-size:1.5rem }.feedback-sheet form { display:grid; gap:1rem; max-width:38rem; margin:auto; padding:1rem; border:1px solid var(--casa-border); border-radius:var(--radius-operation); background:var(--casa-paper) }.feedback-sheet fieldset { border:0; padding:0 }.rating-row { display:grid; grid-template-columns:repeat(5,1fr); gap:.35rem; margin-top:.5rem }.rating-row label { position:relative; min-height:52px; display:grid; place-items:center; border:1px solid var(--casa-border); border-radius:var(--radius-control); color:var(--casa-brass); font-size:1.6rem }.rating-row input { position:absolute; opacity:0 }.rating-row label:has(input:checked) { border-color:var(--casa-palm); background:var(--casa-success-bg) }.feedback-sheet label>span { display:block; margin-bottom:.35rem; font-weight:700 }.feedback-sheet textarea { width:100%; border:1px solid var(--casa-border-strong); border-radius:var(--radius-control); padding:.75rem; font:inherit; resize:vertical }.feedback-sheet .primary { width:100%; min-height:48px }.sr-only { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0) }
</style>
