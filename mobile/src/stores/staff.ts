import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  apiError,
  completeStaffAppointment,
  setStaffAppointmentNoShow,
  staffAppointments,
  staffCommissions,
  staffCustomer,
  staffCustomers,
  staffDashboard,
  staffFeedback,
  staffTransactions,
  type OperationalAppointment,
  type OperationalTransaction,
  type StaffCommission,
  type StaffCustomerDetail,
  type StaffCustomerSummary,
  type StaffDashboard,
  type StaffFeedback,
} from '../lib/api'

const emptyMeta = () => ({ current_page: 1, last_page: 1, per_page: 15, total: 0, from: null as number | null, to: null as number | null })

export const useStaffStore = defineStore('staff', () => {
  const dashboard = ref<StaffDashboard | null>(null)
  const appointments = ref<OperationalAppointment[]>([])
  const appointmentSummary = ref({ confirmed: 0, completed: 0, no_show: 0 })
  const appointmentMeta = ref(emptyMeta())
  const appointmentStatus = ref(''); const appointmentDate = ref(''); const appointmentSearch = ref('')
  const customers = ref<StaffCustomerSummary[]>([]); const customerMeta = ref(emptyMeta()); const customerSearch = ref(''); const selectedCustomer = ref<StaffCustomerDetail | null>(null)
  const transactions = ref<OperationalTransaction[]>([]); const transactionSummary = ref({ paid: '0.00', unpaid_count: 0, partial_count: 0 }); const transactionMeta = ref(emptyMeta()); const transactionStatus = ref(''); const transactionSearch = ref('')
  const feedback = ref<StaffFeedback[]>([]); const feedbackMeta = ref(emptyMeta()); const feedbackSentiment = ref(''); const feedbackSearch = ref('')
  const commissions = ref<StaffCommission[]>([]); const commissionSummary = ref({ pending: '0.00', paid: '0.00', net: '0.00' }); const commissionMeta = ref(emptyMeta()); const commissionStatus = ref('')
  const loading = ref(false); const working = ref(false); const error = ref(''); const notice = ref(''); const fields = ref<Record<string, string[]>>({})

  async function loadDashboard(): Promise<void> { await run(async () => { dashboard.value = await staffDashboard() }) }
  async function loadAppointments(page = 1): Promise<void> { await run(async () => { const response = await staffAppointments({ page, status: appointmentStatus.value || undefined, date: appointmentDate.value || undefined, q: appointmentSearch.value.trim() || undefined }); appointments.value = response.data; appointmentSummary.value = response.summary; appointmentMeta.value = response.meta }) }
  async function noShow(item: OperationalAppointment, reason?: string): Promise<boolean> { return mutate(() => setStaffAppointmentNoShow(item.id, reason), async () => { await Promise.all([loadAppointments(appointmentMeta.value.current_page), loadDashboard()]) }) }
  async function complete(item: OperationalAppointment, payload: Record<string, unknown>): Promise<boolean> { return mutate(() => completeStaffAppointment(item.id, payload), async () => { await Promise.all([loadAppointments(appointmentMeta.value.current_page), loadDashboard(), loadTransactions(1), loadCommissions(1)]) }) }
  async function loadCustomers(page = 1): Promise<void> { await run(async () => { const response = await staffCustomers({ page, q: customerSearch.value.trim() || undefined }); customers.value = response.data; customerMeta.value = response.meta }) }
  async function openCustomer(id: number): Promise<void> { await run(async () => { selectedCustomer.value = await staffCustomer(id) }) }
  async function loadTransactions(page = 1): Promise<void> { await run(async () => { const response = await staffTransactions({ page, payment_status: transactionStatus.value || undefined, q: transactionSearch.value.trim() || undefined }); transactions.value = response.data; transactionSummary.value = response.summary; transactionMeta.value = response.meta }) }
  async function loadFeedback(page = 1): Promise<void> { await run(async () => { const response = await staffFeedback({ page, sentiment: feedbackSentiment.value || undefined, q: feedbackSearch.value.trim() || undefined }); feedback.value = response.data; feedbackMeta.value = response.meta }) }
  async function loadCommissions(page = 1): Promise<void> { await run(async () => { const response = await staffCommissions({ page, status: commissionStatus.value || undefined }); commissions.value = response.data; commissionSummary.value = response.summary; commissionMeta.value = response.meta }) }

  async function run(task: () => Promise<void>): Promise<void> { loading.value = true; clearMessages(); try { await task() } catch (reason) { capture(reason) } finally { loading.value = false } }
  async function mutate(task: () => Promise<{ message: string }>, refresh: () => Promise<void>): Promise<boolean> { working.value = true; clearMessages(); try { const response = await task(); await refresh(); notice.value = response.message; return true } catch (reason) { capture(reason); return false } finally { working.value = false } }
  function clearMessages(): void { error.value = ''; notice.value = ''; fields.value = {} }
  function capture(reason: unknown): void { const failure = apiError(reason); error.value = failure.message; fields.value = failure.fields ?? {} }

  return { dashboard, appointments, appointmentSummary, appointmentMeta, appointmentStatus, appointmentDate, appointmentSearch, customers, customerMeta, customerSearch, selectedCustomer, transactions, transactionSummary, transactionMeta, transactionStatus, transactionSearch, feedback, feedbackMeta, feedbackSentiment, feedbackSearch, commissions, commissionSummary, commissionMeta, commissionStatus, loading, working, error, notice, fields, loadDashboard, loadAppointments, noShow, complete, loadCustomers, openCustomer, loadTransactions, loadFeedback, loadCommissions, clearMessages }
})
