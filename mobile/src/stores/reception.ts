import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  apiError,
  completeReceptionAppointment,
  createReceptionAppointment,
  createReceptionTransaction,
  receptionAppointmentOptions,
  receptionAppointments,
  receptionAvailableTherapists,
  receptionCustomer,
  receptionCustomers,
  receptionDashboard,
  receptionTransactionOptions,
  receptionTransactions,
  setReceptionAppointmentOutcome,
  updateReceptionAppointment,
  updateReceptionCustomer,
  updateReceptionTransaction,
  type BookingTherapist,
  type OperationalAppointment,
  type OperationalTransaction,
  type ReceptionAppointmentOptions,
  type ReceptionCustomerDetail,
  type ReceptionCustomerSummary,
  type ReceptionTransactionOptions,
} from '../lib/api'
import { hasMobileData, invalidateMobileData, loadMobileData, OPERATIONAL_TTL_MS, REFERENCE_TTL_MS } from '../lib/mobileDataCache'

const emptyMeta = () => ({ current_page: 1, last_page: 1, per_page: 15, total: 0, from: null as number | null, to: null as number | null })

export const useReceptionStore = defineStore('reception', () => {
  const endpointPrefix = ref<'reception' | 'admin'>('reception')
  const dashboard = ref<{ summary: { today: number; upcoming: number; customers: number; payments_today: string }; today_appointments: OperationalAppointment[] } | null>(null)
  const appointments = ref<OperationalAppointment[]>([])
  const appointmentSummary = ref({ confirmed: 0, completed: 0, cancelled: 0 })
  const appointmentMeta = ref(emptyMeta())
  const appointmentStatus = ref('')
  const appointmentDate = ref('')
  const appointmentSearch = ref('')
  const appointmentOptions = ref<ReceptionAppointmentOptions | null>(null)
  const availableTherapists = ref<BookingTherapist[]>([])
  const customers = ref<ReceptionCustomerSummary[]>([])
  const customerMeta = ref(emptyMeta())
  const customerSearch = ref('')
  const selectedCustomer = ref<ReceptionCustomerDetail | null>(null)
  const transactions = ref<OperationalTransaction[]>([])
  const transactionSummary = ref({ paid: '0.00', unpaid_count: 0, partial_count: 0 })
  const transactionMeta = ref(emptyMeta())
  const transactionStatus = ref('')
  const transactionSearch = ref('')
  const transactionOptions = ref<ReceptionTransactionOptions | null>(null)
  const loading = ref(false)
  const refreshing = ref(false)
  const working = ref(false)
  const error = ref('')
  const notice = ref('')
  const fields = ref<Record<string, string[]>>({})

  function configurePrefix(prefix: 'reception' | 'admin'): void {
    if (endpointPrefix.value === prefix) return
    endpointPrefix.value = prefix; appointmentOptions.value = null; transactionOptions.value = null; clearMessages()
  }
  const prefix = () => `${endpointPrefix.value}:`
  const dashboardKey = () => `${prefix()}dashboard`
  const appointmentsKey = (page = 1) => `${prefix()}appointments:${page}:${appointmentStatus.value}:${appointmentDate.value}:${appointmentSearch.value.trim()}`
  const customersKey = (page = 1) => `${prefix()}customers:${page}:${customerSearch.value.trim()}`
  const transactionsKey = (page = 1) => `${prefix()}transactions:${page}:${transactionStatus.value}:${transactionSearch.value.trim()}`
  const hasDashboard = () => hasMobileData(dashboardKey())
  const hasAppointments = (page = 1) => hasMobileData(appointmentsKey(page))
  const hasCustomers = (page = 1) => hasMobileData(customersKey(page))
  const hasTransactions = (page = 1) => hasMobileData(transactionsKey(page))
  async function loadDashboard(force = false): Promise<void> { await runCached(dashboardKey(), OPERATIONAL_TTL_MS, async () => { dashboard.value = await receptionDashboard(endpointPrefix.value) }, force) }
  async function loadAppointments(page = 1, force = false): Promise<void> {
    await runCached(appointmentsKey(page), OPERATIONAL_TTL_MS, async () => {
      const response = await receptionAppointments({ page, status: appointmentStatus.value || undefined, date: appointmentDate.value || undefined, q: appointmentSearch.value.trim() || undefined }, endpointPrefix.value)
      appointments.value = response.data; appointmentSummary.value = response.summary; appointmentMeta.value = response.meta
    }, force)
  }
  async function loadAppointmentOptions(force = false): Promise<void> { await runCached(`${prefix()}appointment-options`, REFERENCE_TTL_MS, async () => { appointmentOptions.value = await receptionAppointmentOptions(endpointPrefix.value) }, force) }
  async function findTherapists(serviceId: number, startsAt: string, addonCodes: string[], appointmentId?: number): Promise<void> {
    working.value = true; clearMessages()
    try { availableTherapists.value = await receptionAvailableTherapists({ service_id: serviceId, starts_at: startsAt, appointment_id: appointmentId, addon_codes: addonCodes }, endpointPrefix.value) }
    catch (reason) { capture(reason); availableTherapists.value = [] }
    finally { working.value = false }
  }
  async function saveAppointment(payload: Record<string, unknown>, id?: number): Promise<boolean> {
    return mutate(async () => id ? updateReceptionAppointment(id, payload, endpointPrefix.value) : createReceptionAppointment(payload, endpointPrefix.value), async () => { invalidateMobileData(prefix()); await Promise.all([loadAppointments(1, true), loadDashboard(true)]) })
  }
  async function outcome(appointment: OperationalAppointment, status: 'cancelled' | 'no_show', reason?: string): Promise<boolean> {
    return mutate(() => setReceptionAppointmentOutcome(appointment.id, status, reason, endpointPrefix.value), async () => { invalidateMobileData(prefix()); await Promise.all([loadAppointments(appointmentMeta.value.current_page, true), loadDashboard(true)]) })
  }
  async function complete(appointment: OperationalAppointment, payload: Record<string, unknown>): Promise<boolean> {
    return mutate(() => completeReceptionAppointment(appointment.id, payload, endpointPrefix.value), async () => { invalidateMobileData(prefix()); await Promise.all([loadAppointments(appointmentMeta.value.current_page, true), loadDashboard(true), loadTransactions(1, true)]) })
  }
  async function loadCustomers(page = 1, force = false): Promise<void> {
    await runCached(customersKey(page), OPERATIONAL_TTL_MS, async () => { const response = await receptionCustomers({ page, q: customerSearch.value.trim() || undefined }, endpointPrefix.value); customers.value = response.data; customerMeta.value = response.meta }, force)
  }
  async function openCustomer(id: number): Promise<void> { await run(async () => { selectedCustomer.value = await receptionCustomer(id, endpointPrefix.value) }) }
  async function saveCustomer(id: number, payload: Record<string, unknown>): Promise<boolean> {
    return mutate(() => updateReceptionCustomer(id, payload, endpointPrefix.value), async () => { invalidateMobileData(prefix()); selectedCustomer.value = await receptionCustomer(id, endpointPrefix.value); await loadCustomers(customerMeta.value.current_page, true) })
  }
  async function loadTransactions(page = 1, force = false): Promise<void> {
    await runCached(transactionsKey(page), OPERATIONAL_TTL_MS, async () => { const response = await receptionTransactions({ page, payment_status: transactionStatus.value || undefined, q: transactionSearch.value.trim() || undefined }, endpointPrefix.value); transactions.value = response.data; transactionSummary.value = response.summary; transactionMeta.value = response.meta }, force)
  }
  async function loadTransactionOptions(force = false): Promise<void> { await runCached(`${prefix()}transaction-options`, REFERENCE_TTL_MS, async () => { transactionOptions.value = await receptionTransactionOptions(endpointPrefix.value) }, force) }
  async function saveTransaction(payload: Record<string, unknown>, id?: number): Promise<boolean> {
    return mutate(async () => id ? updateReceptionTransaction(id, payload, endpointPrefix.value) : createReceptionTransaction(payload, endpointPrefix.value), async () => { invalidateMobileData(prefix()); await Promise.all([loadTransactions(1, true), loadDashboard(true)]) })
  }

  let initialRequests = 0; let refreshRequests = 0
  async function runCached(key: string, ttl: number, task: () => Promise<void>, force = false): Promise<void> {
    const initial = !hasMobileData(key)
    if (initial) { initialRequests += 1; loading.value = true } else { refreshRequests += 1; refreshing.value = true }
    clearMessages()
    try { await loadMobileData(key, ttl, task, force) } catch (reason) { capture(reason) }
    finally {
      if (initial) { initialRequests -= 1; loading.value = initialRequests > 0 } else { refreshRequests -= 1; refreshing.value = refreshRequests > 0 }
    }
  }

  async function run(task: () => Promise<void>): Promise<void> {
    loading.value = true; clearMessages()
    try { await task() } catch (reason) { capture(reason) } finally { loading.value = false }
  }
  async function mutate(task: () => Promise<{ message: string }>, refresh: () => Promise<void>): Promise<boolean> {
    working.value = true; clearMessages()
    try { const response = await task(); await refresh(); notice.value = response.message; return true }
    catch (reason) { capture(reason); return false } finally { working.value = false }
  }
  function clearMessages(): void { error.value = ''; notice.value = ''; fields.value = {} }
  function capture(reason: unknown): void { const failure = apiError(reason); error.value = failure.message; fields.value = failure.fields ?? {} }
  function reset(): void {
    dashboard.value = null; appointments.value = []; appointmentSummary.value = { confirmed: 0, completed: 0, cancelled: 0 }; appointmentMeta.value = emptyMeta(); appointmentOptions.value = null; availableTherapists.value = []
    customers.value = []; customerMeta.value = emptyMeta(); selectedCustomer.value = null; transactions.value = []; transactionSummary.value = { paid: '0.00', unpaid_count: 0, partial_count: 0 }; transactionMeta.value = emptyMeta(); transactionOptions.value = null
    appointmentStatus.value = ''; appointmentDate.value = ''; appointmentSearch.value = ''; customerSearch.value = ''; transactionStatus.value = ''; transactionSearch.value = ''; clearMessages()
  }

  return {
    dashboard, appointments, appointmentSummary, appointmentMeta, appointmentStatus, appointmentDate, appointmentSearch, appointmentOptions, availableTherapists,
    customers, customerMeta, customerSearch, selectedCustomer, transactions, transactionSummary, transactionMeta, transactionStatus, transactionSearch, transactionOptions,
    endpointPrefix, configurePrefix, loading, refreshing, working, error, notice, fields, hasDashboard, hasAppointments, hasCustomers, hasTransactions, loadDashboard, loadAppointments, loadAppointmentOptions, findTherapists, saveAppointment, outcome, complete,
    loadCustomers, openCustomer, saveCustomer, loadTransactions, loadTransactionOptions, saveTransaction, clearMessages, reset,
  }
})
