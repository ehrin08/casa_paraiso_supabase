import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  apiError,
  customerFeedback,
  submitCustomerFeedback,
  type EligibleFeedbackAppointment,
  type MobileFeedback,
} from '../lib/api'
import { hasMobileData, invalidateMobileData, loadMobileData, OPERATIONAL_TTL_MS } from '../lib/mobileDataCache'

export const useCustomerFeedbackStore = defineStore('customerFeedback', () => {
  const feedback = ref<MobileFeedback[]>([])
  const eligibleAppointments = ref<EligibleFeedbackAppointment[]>([])
  const summary = ref({ awaiting_feedback: 0, submitted: 0 })
  const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0, from: null as number | null, to: null as number | null })
  const loading = ref(false)
  const refreshing = ref(false)
  const submitting = ref(false)
  const error = ref('')
  const notice = ref('')
  const fields = ref<Record<string, string[]>>({})

  const cacheKey = (page = 1) => `customer:feedback:${page}`
  const hasLoaded = (page = 1) => hasMobileData(cacheKey(page))
  async function load(page = 1, force = false): Promise<void> {
    const initial = !hasLoaded(page)
    if (initial) loading.value = true; else refreshing.value = true
    error.value = ''
    try {
      await loadMobileData(cacheKey(page), OPERATIONAL_TTL_MS, async () => {
        const response = await customerFeedback(page)
        feedback.value = response.data
        eligibleAppointments.value = response.eligible_appointments
        summary.value = response.summary
        meta.value = response.meta
      }, force)
    } catch (reason) {
      error.value = apiError(reason).message
    } finally {
      loading.value = false; refreshing.value = false
    }
  }

  async function submit(appointmentId: number, rating: number, comment: string): Promise<boolean> {
    submitting.value = true
    error.value = ''
    notice.value = ''
    fields.value = {}
    try {
      const response = await submitCustomerFeedback({
        appointment_id: appointmentId,
        rating,
        comment: comment.trim() || undefined,
      })
      notice.value = response.message
      invalidateMobileData('customer:')
      await load(1, true)
      return true
    } catch (reason) {
      const failure = apiError(reason)
      error.value = failure.message
      fields.value = failure.fields ?? {}
      return false
    } finally {
      submitting.value = false
    }
  }

  function reset(): void { feedback.value = []; eligibleAppointments.value = []; summary.value = { awaiting_feedback: 0, submitted: 0 }; meta.value = { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null }; error.value = ''; notice.value = ''; fields.value = {} }
  return { feedback, eligibleAppointments, summary, meta, loading, refreshing, submitting, error, notice, fields, hasLoaded, load, submit, reset }
})
