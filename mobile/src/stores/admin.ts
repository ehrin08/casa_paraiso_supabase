import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  adminDashboard, adminServices, adminStaff, adminStaffDetail, adminStaffOptions, apiError,
  addAdminRosterShift, adminRoster, copyAdminRoster, createAdminScheduleException, createAdminService, createAdminStaff, createAdminWeeklySchedule, deleteAdminRosterShift, deleteAdminScheduleException, deleteAdminWeeklySchedule, publishAdminRoster,
  toggleAdminService, updateAdminService, updateAdminStaff,
  type AdminDashboard, type AdminRoster, type AdminService, type AdminStaffDetail, type AdminStaffOptions, type AdminStaffSummary,
} from '../lib/api'
import { hasMobileData, invalidateMobileData, loadMobileData, OPERATIONAL_TTL_MS, REFERENCE_TTL_MS } from '../lib/mobileDataCache'

const emptyMeta = () => ({ current_page: 1, last_page: 1, per_page: 15, total: 0, from: null as number | null, to: null as number | null })

export const useAdminStore = defineStore('admin', () => {
  const dashboard = ref<AdminDashboard | null>(null)
  const services = ref<AdminService[]>([]); const serviceSummary = ref({ active: 0, inactive: 0 }); const serviceMeta = ref(emptyMeta()); const serviceSearch = ref(''); const serviceStatus = ref('')
  const staff = ref<AdminStaffSummary[]>([]); const staffSummary = ref({ active: 0, inactive: 0, bookable: 0 }); const staffMeta = ref(emptyMeta()); const staffSearch = ref(''); const staffStatus = ref(''); const staffBookable = ref('')
  const staffOptions = ref<AdminStaffOptions | null>(null); const selectedStaff = ref<AdminStaffDetail | null>(null)
  const roster = ref<AdminRoster | null>(null); const rosterWeek = ref('')
  const loading = ref(false); const refreshing = ref(false); const working = ref(false); const error = ref(''); const notice = ref(''); const fields = ref<Record<string, string[]>>({})

  const dashboardKey='admin:dashboard';const servicesKey=(page=1)=>`admin:services:${page}:${serviceSearch.value.trim()}:${serviceStatus.value}`;const staffKey=(page=1)=>`admin:staff:${page}:${staffSearch.value.trim()}:${staffStatus.value}:${staffBookable.value}`
  const hasDashboard=()=>hasMobileData(dashboardKey);const hasServices=(page=1)=>hasMobileData(servicesKey(page));const hasStaff=(page=1)=>hasMobileData(staffKey(page))
  async function loadDashboard(force=false): Promise<void> { await runCached(dashboardKey,OPERATIONAL_TTL_MS,async () => { dashboard.value = await adminDashboard() },force) }
  async function loadServices(page = 1,force=false): Promise<void> { await runCached(servicesKey(page),OPERATIONAL_TTL_MS,async () => { const response = await adminServices({ page, q: serviceSearch.value.trim() || undefined, status: serviceStatus.value || undefined }); services.value = response.data; serviceSummary.value = response.summary; serviceMeta.value = response.meta },force) }
  async function saveService(payload: Record<string, unknown>, id?: number): Promise<boolean> { return mutate(() => id ? updateAdminService(id, payload) : createAdminService(payload), async () => { invalidateMobileData('admin:'); await Promise.all([loadServices(id ? serviceMeta.value.current_page : 1,true), loadDashboard(true)]) }) }
  async function toggleService(id: number): Promise<boolean> { return mutate(() => toggleAdminService(id), async () => { invalidateMobileData('admin:'); await Promise.all([loadServices(serviceMeta.value.current_page,true), loadDashboard(true)]) }) }
  async function loadStaff(page = 1,force=false): Promise<void> { await runCached(staffKey(page),OPERATIONAL_TTL_MS,async () => { const response = await adminStaff({ page, q: staffSearch.value.trim() || undefined, status: staffStatus.value || undefined, bookable: staffBookable.value || undefined }); staff.value = response.data; staffSummary.value = response.summary; staffMeta.value = response.meta },force) }
  async function loadStaffOptions(force=false): Promise<void> { await runCached('admin:staff-options',REFERENCE_TTL_MS,async () => { staffOptions.value = await adminStaffOptions() },force) }
  async function openStaff(id: number): Promise<void> { await run(async () => { selectedStaff.value = await adminStaffDetail(id) }) }
  async function saveStaff(payload: Record<string, unknown>, id?: number): Promise<boolean> { return mutate(() => id ? updateAdminStaff(id, payload) : createAdminStaff(payload), async () => { invalidateMobileData('admin:'); await loadStaff(id ? staffMeta.value.current_page : 1,true); if (id) selectedStaff.value = await adminStaffDetail(id) }) }
  async function addWeeklySchedule(staffId: number, payload: Record<string, unknown>): Promise<boolean> { return mutate(() => createAdminWeeklySchedule(staffId, payload), async () => { selectedStaff.value = await adminStaffDetail(staffId) }) }
  async function removeWeeklySchedule(staffId: number, id: number): Promise<boolean> { return mutate(() => deleteAdminWeeklySchedule(staffId, id), async () => { selectedStaff.value = await adminStaffDetail(staffId) }) }
  async function addException(staffId: number, payload: Record<string, unknown>): Promise<boolean> { return mutate(() => createAdminScheduleException(staffId, payload), async () => { selectedStaff.value = await adminStaffDetail(staffId) }) }
  async function removeException(staffId: number, id: number): Promise<boolean> { return mutate(() => deleteAdminScheduleException(staffId, id), async () => { selectedStaff.value = await adminStaffDetail(staffId) }) }
  async function loadRoster(week: string,force=false): Promise<void> { rosterWeek.value = week; await runCached(`admin:roster:${week}`,OPERATIONAL_TTL_MS,async () => { roster.value = await adminRoster(week) },force) }
  async function copyRoster(): Promise<boolean> { if (!rosterWeek.value) return false; return rosterMutate(() => copyAdminRoster(rosterWeek.value), 'Previous published week copied into draft.') }
  async function addRosterShift(payload: Record<string, unknown>): Promise<boolean> { if (!roster.value) return false; return rosterMutate(() => addAdminRosterShift(roster.value!.schedule_week_id, payload), 'Draft shift added.') }
  async function removeRosterShift(id: number): Promise<boolean> { if (!roster.value) return false; return rosterMutate(() => deleteAdminRosterShift(roster.value!.schedule_week_id, id), 'Draft shift removed.') }
  async function publishRoster(): Promise<boolean> { if (!roster.value) return false; return rosterMutate(() => publishAdminRoster(roster.value!.schedule_week_id), 'Weekly roster published.') }

  let initialRequests=0;let refreshRequests=0
  async function runCached(key:string,ttl:number,task:()=>Promise<void>,force=false):Promise<void>{const initial=!hasMobileData(key);if(initial){initialRequests++;loading.value=true}else{refreshRequests++;refreshing.value=true}clear();try{await loadMobileData(key,ttl,task,force)}catch(reason){capture(reason)}finally{if(initial){initialRequests--;loading.value=initialRequests>0}else{refreshRequests--;refreshing.value=refreshRequests>0}}}
  async function run(task: () => Promise<void>): Promise<void> { loading.value = true; clear(); try { await task() } catch (reason) { capture(reason) } finally { loading.value = false } }
  async function mutate(task: () => Promise<{ message: string }>, refresh: () => Promise<void>): Promise<boolean> { working.value = true; clear(); try { const result = await task(); await refresh(); notice.value = result.message; return true } catch (reason) { capture(reason); return false } finally { working.value = false } }
  async function rosterMutate(task: () => Promise<AdminRoster>, message: string): Promise<boolean> { working.value = true; clear(); try { roster.value = await task(); notice.value = message; return true } catch (reason) { capture(reason); return false } finally { working.value = false } }
  function clear(): void { error.value = ''; notice.value = ''; fields.value = {} }
  function capture(reason: unknown): void { const failure = apiError(reason); error.value = failure.message; fields.value = failure.fields ?? {} }
  function reset():void{dashboard.value=null;services.value=[];serviceSummary.value={active:0,inactive:0};serviceMeta.value=emptyMeta();staff.value=[];staffSummary.value={active:0,inactive:0,bookable:0};staffMeta.value=emptyMeta();staffOptions.value=null;selectedStaff.value=null;roster.value=null;rosterWeek.value='';serviceSearch.value='';serviceStatus.value='';staffSearch.value='';staffStatus.value='';staffBookable.value='';clear()}

  return { dashboard, services, serviceSummary, serviceMeta, serviceSearch, serviceStatus, staff, staffSummary, staffMeta, staffSearch, staffStatus, staffBookable, staffOptions, selectedStaff, roster, rosterWeek, loading, refreshing, working, error, notice, fields, hasDashboard, hasServices, hasStaff, loadDashboard, loadServices, saveService, toggleService, loadStaff, loadStaffOptions, openStaff, saveStaff, addWeeklySchedule, removeWeeklySchedule, addException, removeException, loadRoster, copyRoster, addRosterShift, removeRosterShift, publishRoster, clear, reset }
})
