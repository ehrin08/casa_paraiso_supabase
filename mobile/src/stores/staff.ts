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
import { hasMobileData, invalidateMobileData, loadMobileData, OPERATIONAL_TTL_MS } from '../lib/mobileDataCache'

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
  const loading = ref(false); const refreshing = ref(false); const working = ref(false); const error = ref(''); const notice = ref(''); const fields = ref<Record<string, string[]>>({})

  const dashboardKey = 'staff:dashboard'; const appointmentsKey = (page=1)=>`staff:appointments:${page}:${appointmentStatus.value}:${appointmentDate.value}:${appointmentSearch.value.trim()}`; const customersKey=(page=1)=>`staff:customers:${page}:${customerSearch.value.trim()}`; const transactionsKey=(page=1)=>`staff:transactions:${page}:${transactionStatus.value}:${transactionSearch.value.trim()}`; const feedbackKey=(page=1)=>`staff:feedback:${page}:${feedbackSentiment.value}:${feedbackSearch.value.trim()}`; const commissionsKey=(page=1)=>`staff:commissions:${page}:${commissionStatus.value}`
  const hasDashboard=()=>hasMobileData(dashboardKey); const hasAppointments=(page=1)=>hasMobileData(appointmentsKey(page)); const hasCustomers=(page=1)=>hasMobileData(customersKey(page)); const hasTransactions=(page=1)=>hasMobileData(transactionsKey(page)); const hasFeedback=(page=1)=>hasMobileData(feedbackKey(page)); const hasCommissions=(page=1)=>hasMobileData(commissionsKey(page))
  async function loadDashboard(force=false): Promise<void> { await runCached(dashboardKey, async () => { dashboard.value = await staffDashboard() }, force) }
  async function loadAppointments(page = 1, force=false): Promise<void> { await runCached(appointmentsKey(page), async () => { const response = await staffAppointments({ page, status: appointmentStatus.value || undefined, date: appointmentDate.value || undefined, q: appointmentSearch.value.trim() || undefined }); appointments.value = response.data; appointmentSummary.value = response.summary; appointmentMeta.value = response.meta }, force) }
  async function noShow(item: OperationalAppointment, reason?: string): Promise<boolean> { return mutate(() => setStaffAppointmentNoShow(item.id, reason), async () => { invalidateMobileData('staff:'); await Promise.all([loadAppointments(appointmentMeta.value.current_page, true), loadDashboard(true)]) }) }
  async function complete(item: OperationalAppointment, payload: Record<string, unknown>): Promise<boolean> { return mutate(() => completeStaffAppointment(item.id, payload), async () => { invalidateMobileData('staff:'); await Promise.all([loadAppointments(appointmentMeta.value.current_page, true), loadDashboard(true), loadTransactions(1, true), loadCommissions(1, true)]) }) }
  async function loadCustomers(page = 1, force=false): Promise<void> { await runCached(customersKey(page), async () => { const response = await staffCustomers({ page, q: customerSearch.value.trim() || undefined }); customers.value = response.data; customerMeta.value = response.meta }, force) }
  async function openCustomer(id: number): Promise<void> { await run(async () => { selectedCustomer.value = await staffCustomer(id) }) }
  async function loadTransactions(page = 1, force=false): Promise<void> { await runCached(transactionsKey(page), async () => { const response = await staffTransactions({ page, payment_status: transactionStatus.value || undefined, q: transactionSearch.value.trim() || undefined }); transactions.value = response.data; transactionSummary.value = response.summary; transactionMeta.value = response.meta }, force) }
  async function loadFeedback(page = 1, force=false): Promise<void> { await runCached(feedbackKey(page), async () => { const response = await staffFeedback({ page, sentiment: feedbackSentiment.value || undefined, q: feedbackSearch.value.trim() || undefined }); feedback.value = response.data; feedbackMeta.value = response.meta }, force) }
  async function loadCommissions(page = 1, force=false): Promise<void> { await runCached(commissionsKey(page), async () => { const response = await staffCommissions({ page, status: commissionStatus.value || undefined }); commissions.value = response.data; commissionSummary.value = response.summary; commissionMeta.value = response.meta }, force) }

  let initialRequests=0;let refreshRequests=0
  async function runCached(key:string,task:()=>Promise<void>,force=false):Promise<void>{const initial=!hasMobileData(key);if(initial){initialRequests++;loading.value=true}else{refreshRequests++;refreshing.value=true}clearMessages();try{await loadMobileData(key,OPERATIONAL_TTL_MS,task,force)}catch(reason){capture(reason)}finally{if(initial){initialRequests--;loading.value=initialRequests>0}else{refreshRequests--;refreshing.value=refreshRequests>0}}}
  async function run(task: () => Promise<void>): Promise<void> { loading.value = true; clearMessages(); try { await task() } catch (reason) { capture(reason) } finally { loading.value = false } }
  async function mutate(task: () => Promise<{ message: string }>, refresh: () => Promise<void>): Promise<boolean> { working.value = true; clearMessages(); try { const response = await task(); await refresh(); notice.value = response.message; return true } catch (reason) { capture(reason); return false } finally { working.value = false } }
  function clearMessages(): void { error.value = ''; notice.value = ''; fields.value = {} }
  function capture(reason: unknown): void { const failure = apiError(reason); error.value = failure.message; fields.value = failure.fields ?? {} }
  function reset():void{dashboard.value=null;appointments.value=[];appointmentSummary.value={confirmed:0,completed:0,no_show:0};appointmentMeta.value=emptyMeta();customers.value=[];customerMeta.value=emptyMeta();selectedCustomer.value=null;transactions.value=[];transactionSummary.value={paid:'0.00',unpaid_count:0,partial_count:0};transactionMeta.value=emptyMeta();feedback.value=[];feedbackMeta.value=emptyMeta();commissions.value=[];commissionSummary.value={pending:'0.00',paid:'0.00',net:'0.00'};commissionMeta.value=emptyMeta();appointmentStatus.value='';appointmentDate.value='';appointmentSearch.value='';customerSearch.value='';transactionStatus.value='';transactionSearch.value='';feedbackSentiment.value='';feedbackSearch.value='';commissionStatus.value='';clearMessages()}

  return { dashboard, appointments, appointmentSummary, appointmentMeta, appointmentStatus, appointmentDate, appointmentSearch, customers, customerMeta, customerSearch, selectedCustomer, transactions, transactionSummary, transactionMeta, transactionStatus, transactionSearch, feedback, feedbackMeta, feedbackSentiment, feedbackSearch, commissions, commissionSummary, commissionMeta, commissionStatus, loading, refreshing, working, error, notice, fields, hasDashboard, hasAppointments, hasCustomers, hasTransactions, hasFeedback, hasCommissions, loadDashboard, loadAppointments, noShow, complete, loadCustomers, openCustomer, loadTransactions, loadFeedback, loadCommissions, clearMessages, reset }
})
