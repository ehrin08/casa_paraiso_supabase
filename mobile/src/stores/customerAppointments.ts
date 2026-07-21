import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiError, cancelCustomerAppointment, customerAppointments, type MobileAppointment } from '../lib/api'
import { hasMobileData, invalidateMobileData, loadMobileData, OPERATIONAL_TTL_MS } from '../lib/mobileDataCache'

export const useCustomerAppointmentsStore = defineStore('customerAppointments', () => {
  const appointments = ref<MobileAppointment[]>([])
  const summary = ref({ upcoming: 0, completed: 0, cancelled: 0 })
  const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0, from: null as number | null, to: null as number | null })
  const status = ref('')
  const loading = ref(false)
  const refreshing = ref(false)
  const cancellingId = ref<number | null>(null)
  const error = ref('')
  const notice = ref('')

  function cacheKey(page = 1): string { return `customer:appointments:${page}:${status.value}` }
  function hasLoaded(page = 1): boolean { return hasMobileData(cacheKey(page)) }

  async function load(page = 1, force = false): Promise<void> {
    const initial = !hasLoaded(page)
    if (initial) loading.value = true; else refreshing.value = true
    error.value = ''
    try {
      await loadMobileData(cacheKey(page), OPERATIONAL_TTL_MS, async () => {
        const response = await customerAppointments({ page, status: status.value || undefined })
        appointments.value = response.data
        summary.value = response.summary
        meta.value = response.meta
      }, force)
    } catch (reason) {
      error.value = apiError(reason).message
    } finally {
      loading.value = false; refreshing.value = false
    }
  }

  async function cancel(appointment: MobileAppointment): Promise<void> {
    cancellingId.value = appointment.id
    error.value = ''
    notice.value = ''
    try {
      const response = await cancelCustomerAppointment(appointment.id)
      notice.value = response.message
      invalidateMobileData('customer:')
      await load(meta.value.current_page, true)
    } catch (reason) {
      error.value = apiError(reason).message
    } finally {
      cancellingId.value = null
    }
  }

  function reset(): void { appointments.value = []; summary.value = { upcoming: 0, completed: 0, cancelled: 0 }; meta.value = { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null }; status.value = ''; error.value = ''; notice.value = '' }

  return { appointments, summary, meta, status, loading, refreshing, cancellingId, error, notice, hasLoaded, load, cancel, reset }
})
