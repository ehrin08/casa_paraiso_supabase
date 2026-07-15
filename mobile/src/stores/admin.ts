import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  adminDashboard, adminServices, adminStaff, adminStaffDetail, adminStaffOptions, apiError,
  addAdminRosterShift, adminRoster, copyAdminRoster, createAdminScheduleException, createAdminService, createAdminStaff, createAdminWeeklySchedule, deleteAdminRosterShift, deleteAdminScheduleException, deleteAdminWeeklySchedule, publishAdminRoster,
  toggleAdminService, updateAdminService, updateAdminStaff,
  type AdminDashboard, type AdminRoster, type AdminService, type AdminStaffDetail, type AdminStaffOptions, type AdminStaffSummary,
} from '../lib/api'

const emptyMeta = () => ({ current_page: 1, last_page: 1, per_page: 15, total: 0, from: null as number | null, to: null as number | null })

export const useAdminStore = defineStore('admin', () => {
  const dashboard = ref<AdminDashboard | null>(null)
  const services = ref<AdminService[]>([]); const serviceSummary = ref({ active: 0, inactive: 0 }); const serviceMeta = ref(emptyMeta()); const serviceSearch = ref(''); const serviceStatus = ref('')
  const staff = ref<AdminStaffSummary[]>([]); const staffSummary = ref({ active: 0, inactive: 0, bookable: 0 }); const staffMeta = ref(emptyMeta()); const staffSearch = ref(''); const staffStatus = ref(''); const staffBookable = ref('')
  const staffOptions = ref<AdminStaffOptions | null>(null); const selectedStaff = ref<AdminStaffDetail | null>(null)
  const roster = ref<AdminRoster | null>(null); const rosterWeek = ref('')
  const loading = ref(false); const working = ref(false); const error = ref(''); const notice = ref(''); const fields = ref<Record<string, string[]>>({})

  async function loadDashboard(): Promise<void> { await run(async () => { dashboard.value = await adminDashboard() }) }
  async function loadServices(page = 1): Promise<void> { await run(async () => { const response = await adminServices({ page, q: serviceSearch.value.trim() || undefined, status: serviceStatus.value || undefined }); services.value = response.data; serviceSummary.value = response.summary; serviceMeta.value = response.meta }) }
  async function saveService(payload: Record<string, unknown>, id?: number): Promise<boolean> { return mutate(() => id ? updateAdminService(id, payload) : createAdminService(payload), async () => { await Promise.all([loadServices(id ? serviceMeta.value.current_page : 1), loadDashboard()]) }) }
  async function toggleService(id: number): Promise<boolean> { return mutate(() => toggleAdminService(id), async () => { await Promise.all([loadServices(serviceMeta.value.current_page), loadDashboard()]) }) }
  async function loadStaff(page = 1): Promise<void> { await run(async () => { const response = await adminStaff({ page, q: staffSearch.value.trim() || undefined, status: staffStatus.value || undefined, bookable: staffBookable.value || undefined }); staff.value = response.data; staffSummary.value = response.summary; staffMeta.value = response.meta }) }
  async function loadStaffOptions(): Promise<void> { if (!staffOptions.value) await run(async () => { staffOptions.value = await adminStaffOptions() }) }
  async function openStaff(id: number): Promise<void> { await run(async () => { selectedStaff.value = await adminStaffDetail(id) }) }
  async function saveStaff(payload: Record<string, unknown>, id?: number): Promise<boolean> { return mutate(() => id ? updateAdminStaff(id, payload) : createAdminStaff(payload), async () => { await loadStaff(id ? staffMeta.value.current_page : 1); if (id) selectedStaff.value = await adminStaffDetail(id) }) }
  async function addWeeklySchedule(staffId: number, payload: Record<string, unknown>): Promise<boolean> { return mutate(() => createAdminWeeklySchedule(staffId, payload), async () => { selectedStaff.value = await adminStaffDetail(staffId) }) }
  async function removeWeeklySchedule(staffId: number, id: number): Promise<boolean> { return mutate(() => deleteAdminWeeklySchedule(staffId, id), async () => { selectedStaff.value = await adminStaffDetail(staffId) }) }
  async function addException(staffId: number, payload: Record<string, unknown>): Promise<boolean> { return mutate(() => createAdminScheduleException(staffId, payload), async () => { selectedStaff.value = await adminStaffDetail(staffId) }) }
  async function removeException(staffId: number, id: number): Promise<boolean> { return mutate(() => deleteAdminScheduleException(staffId, id), async () => { selectedStaff.value = await adminStaffDetail(staffId) }) }
  async function loadRoster(week: string): Promise<void> { rosterWeek.value = week; await run(async () => { roster.value = await adminRoster(week) }) }
  async function copyRoster(): Promise<boolean> { if (!rosterWeek.value) return false; return rosterMutate(() => copyAdminRoster(rosterWeek.value), 'Previous published week copied into draft.') }
  async function addRosterShift(payload: Record<string, unknown>): Promise<boolean> { if (!roster.value) return false; return rosterMutate(() => addAdminRosterShift(roster.value!.schedule_week_id, payload), 'Draft shift added.') }
  async function removeRosterShift(id: number): Promise<boolean> { if (!roster.value) return false; return rosterMutate(() => deleteAdminRosterShift(roster.value!.schedule_week_id, id), 'Draft shift removed.') }
  async function publishRoster(): Promise<boolean> { if (!roster.value) return false; return rosterMutate(() => publishAdminRoster(roster.value!.schedule_week_id), 'Weekly roster published.') }

  async function run(task: () => Promise<void>): Promise<void> { loading.value = true; clear(); try { await task() } catch (reason) { capture(reason) } finally { loading.value = false } }
  async function mutate(task: () => Promise<{ message: string }>, refresh: () => Promise<void>): Promise<boolean> { working.value = true; clear(); try { const result = await task(); await refresh(); notice.value = result.message; return true } catch (reason) { capture(reason); return false } finally { working.value = false } }
  async function rosterMutate(task: () => Promise<AdminRoster>, message: string): Promise<boolean> { working.value = true; clear(); try { roster.value = await task(); notice.value = message; return true } catch (reason) { capture(reason); return false } finally { working.value = false } }
  function clear(): void { error.value = ''; notice.value = ''; fields.value = {} }
  function capture(reason: unknown): void { const failure = apiError(reason); error.value = failure.message; fields.value = failure.fields ?? {} }

  return { dashboard, services, serviceSummary, serviceMeta, serviceSearch, serviceStatus, staff, staffSummary, staffMeta, staffSearch, staffStatus, staffBookable, staffOptions, selectedStaff, roster, rosterWeek, loading, working, error, notice, fields, loadDashboard, loadServices, saveService, toggleService, loadStaff, loadStaffOptions, openStaff, saveStaff, addWeeklySchedule, removeWeeklySchedule, addException, removeException, loadRoster, copyRoster, addRosterShift, removeRosterShift, publishRoster, clear }
})
