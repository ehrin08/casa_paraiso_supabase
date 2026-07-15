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
  const working = ref(false)
  const error = ref('')
  const notice = ref('')
  const fields = ref<Record<string, string[]>>({})

  function configurePrefix(prefix: 'reception' | 'admin'): void {
    if (endpointPrefix.value === prefix) return
    endpointPrefix.value = prefix; appointmentOptions.value = null; transactionOptions.value = null; clearMessages()
  }
  async function loadDashboard(): Promise<void> { await run(async () => { dashboard.value = await receptionDashboard(endpointPrefix.value) }) }
  async function loadAppointments(page = 1): Promise<void> {
    await run(async () => {
      const response = await receptionAppointments({ page, status: appointmentStatus.value || undefined, date: appointmentDate.value || undefined, q: appointmentSearch.value.trim() || undefined }, endpointPrefix.value)
      appointments.value = response.data; appointmentSummary.value = response.summary; appointmentMeta.value = response.meta
    })
  }
  async function loadAppointmentOptions(): Promise<void> { if (!appointmentOptions.value) await run(async () => { appointmentOptions.value = await receptionAppointmentOptions(endpointPrefix.value) }) }
  async function findTherapists(serviceId: number, startsAt: string, addonCodes: string[], appointmentId?: number): Promise<void> {
    working.value = true; clearMessages()
    try { availableTherapists.value = await receptionAvailableTherapists({ service_id: serviceId, starts_at: startsAt, appointment_id: appointmentId, addon_codes: addonCodes }, endpointPrefix.value) }
    catch (reason) { capture(reason); availableTherapists.value = [] }
    finally { working.value = false }
  }
  async function saveAppointment(payload: Record<string, unknown>, id?: number): Promise<boolean> {
    return mutate(async () => id ? updateReceptionAppointment(id, payload, endpointPrefix.value) : createReceptionAppointment(payload, endpointPrefix.value), async () => { await Promise.all([loadAppointments(1), loadDashboard()]) })
  }
  async function outcome(appointment: OperationalAppointment, status: 'cancelled' | 'no_show', reason?: string): Promise<boolean> {
    return mutate(() => setReceptionAppointmentOutcome(appointment.id, status, reason, endpointPrefix.value), async () => { await Promise.all([loadAppointments(appointmentMeta.value.current_page), loadDashboard()]) })
  }
  async function complete(appointment: OperationalAppointment, payload: Record<string, unknown>): Promise<boolean> {
    return mutate(() => completeReceptionAppointment(appointment.id, payload, endpointPrefix.value), async () => { await Promise.all([loadAppointments(appointmentMeta.value.current_page), loadDashboard(), loadTransactions(1)]) })
  }
  async function loadCustomers(page = 1): Promise<void> {
    await run(async () => { const response = await receptionCustomers({ page, q: customerSearch.value.trim() || undefined }, endpointPrefix.value); customers.value = response.data; customerMeta.value = response.meta })
  }
  async function openCustomer(id: number): Promise<void> { await run(async () => { selectedCustomer.value = await receptionCustomer(id, endpointPrefix.value) }) }
  async function saveCustomer(id: number, payload: Record<string, unknown>): Promise<boolean> {
    return mutate(() => updateReceptionCustomer(id, payload, endpointPrefix.value), async () => { selectedCustomer.value = await receptionCustomer(id, endpointPrefix.value); await loadCustomers(customerMeta.value.current_page) })
  }
  async function loadTransactions(page = 1): Promise<void> {
    await run(async () => { const response = await receptionTransactions({ page, payment_status: transactionStatus.value || undefined, q: transactionSearch.value.trim() || undefined }, endpointPrefix.value); transactions.value = response.data; transactionSummary.value = response.summary; transactionMeta.value = response.meta })
  }
  async function loadTransactionOptions(): Promise<void> { if (!transactionOptions.value) await run(async () => { transactionOptions.value = await receptionTransactionOptions(endpointPrefix.value) }) }
  async function saveTransaction(payload: Record<string, unknown>, id?: number): Promise<boolean> {
    return mutate(async () => id ? updateReceptionTransaction(id, payload, endpointPrefix.value) : createReceptionTransaction(payload, endpointPrefix.value), async () => { await Promise.all([loadTransactions(1), loadDashboard()]) })
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

  return {
    dashboard, appointments, appointmentSummary, appointmentMeta, appointmentStatus, appointmentDate, appointmentSearch, appointmentOptions, availableTherapists,
    customers, customerMeta, customerSearch, selectedCustomer, transactions, transactionSummary, transactionMeta, transactionStatus, transactionSearch, transactionOptions,
    endpointPrefix, configurePrefix, loading, working, error, notice, fields, loadDashboard, loadAppointments, loadAppointmentOptions, findTherapists, saveAppointment, outcome, complete,
    loadCustomers, openCustomer, saveCustomer, loadTransactions, loadTransactionOptions, saveTransaction, clearMessages,
  }
})
