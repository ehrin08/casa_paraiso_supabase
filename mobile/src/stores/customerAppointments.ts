import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiError, cancelCustomerAppointment, customerAppointments, type MobileAppointment } from '../lib/api'

export const useCustomerAppointmentsStore = defineStore('customerAppointments', () => {
  const appointments = ref<MobileAppointment[]>([])
  const summary = ref({ upcoming: 0, completed: 0, cancelled: 0 })
  const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0, from: null as number | null, to: null as number | null })
  const status = ref('')
  const loading = ref(false)
  const cancellingId = ref<number | null>(null)
  const error = ref('')
  const notice = ref('')

  async function load(page = 1): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const response = await customerAppointments({ page, status: status.value || undefined })
      appointments.value = response.data
      summary.value = response.summary
      meta.value = response.meta
    } catch (reason) {
      error.value = apiError(reason).message
    } finally {
      loading.value = false
    }
  }

  async function cancel(appointment: MobileAppointment): Promise<void> {
    cancellingId.value = appointment.id
    error.value = ''
    notice.value = ''
    try {
      const response = await cancelCustomerAppointment(appointment.id)
      notice.value = response.message
      await load(meta.value.current_page)
    } catch (reason) {
      error.value = apiError(reason).message
    } finally {
      cancellingId.value = null
    }
  }

  return { appointments, summary, meta, status, loading, cancellingId, error, notice, load, cancel }
})
