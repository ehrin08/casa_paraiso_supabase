import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  apiError,
  customerFeedback,
  submitCustomerFeedback,
  type EligibleFeedbackAppointment,
  type MobileFeedback,
} from '../lib/api'

export const useCustomerFeedbackStore = defineStore('customerFeedback', () => {
  const feedback = ref<MobileFeedback[]>([])
  const eligibleAppointments = ref<EligibleFeedbackAppointment[]>([])
  const summary = ref({ awaiting_feedback: 0, submitted: 0 })
  const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0, from: null as number | null, to: null as number | null })
  const loading = ref(false)
  const submitting = ref(false)
  const error = ref('')
  const notice = ref('')
  const fields = ref<Record<string, string[]>>({})

  async function load(page = 1): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const response = await customerFeedback(page)
      feedback.value = response.data
      eligibleAppointments.value = response.eligible_appointments
      summary.value = response.summary
      meta.value = response.meta
    } catch (reason) {
      error.value = apiError(reason).message
    } finally {
      loading.value = false
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
      await load(1)
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

  return { feedback, eligibleAppointments, summary, meta, loading, submitting, error, notice, fields, load, submit }
})
