import axios, { type AxiosInstance } from 'axios'
import { BACKEND_URL } from './pairing'

export interface MobileUser { id: number; name: string; email: string; phone: string | null; role: string; workspace: 'admin' | 'reception' | 'staff' | 'customer'; email_verified: boolean }
export interface ApiError { code: string; message: string; fields?: Record<string, string[]> }
export interface AppointmentParty { id: number; name: string | null }
export interface MobileAppointment {
  id: number
  appointment_number: string
  status: 'confirmed' | 'completed' | 'cancelled' | 'no_show'
  starts_at: string | null
  ends_at: string | null
  customer_notes: string | null
  can_cancel: boolean
  can_submit_feedback: boolean
  service: { id: number; name: string; duration_minutes: number; price: string } | null
  therapist: AppointmentParty | null
  preferred_therapist: AppointmentParty | null
  addons: Array<{ code: string; name: string; price: string; duration_minutes: number }>
  voucher: { id: number; code: string; name: string | null } | null
  expected_amount: string
  feedback: { id: number; rating: number; sentiment: string; submitted_at: string | null } | null
}
export interface AppointmentListResponse {
  data: MobileAppointment[]
  summary: { upcoming: number; completed: number; cancelled: number }
  meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null }
}
export interface BookingTherapist { id: number; name: string | null }
export interface BookingService {
  id: number
  name: string
  description: string | null
  duration_minutes: number
  price: string
  therapists: BookingTherapist[]
}
export interface BookingAddon { code: string; name: string; price: string; duration_minutes: number }
export interface BookingVoucher { id: number; code: string; name: string; expires_at: string | null }
export interface BookingOptions {
  services: BookingService[]
  addons: BookingAddon[]
  vouchers: BookingVoucher[]
  booking_window: { timezone: string; opens_at: string; closes_at: string; slot_interval_minutes: number; lead_time_minutes: number; initial_month: string }
}
export interface AvailabilitySlot { starts_at: string; ends_at: string; time: string; label: string; staff_count: number }
export interface BookingAvailability { month: string; service_id: number; preferred_staff_profile_id: number | null; dates: Record<string, AvailabilitySlot[]> }
export interface EligibleFeedbackAppointment {
  id: number
  appointment_number: string
  completed_at: string | null
  service: { id: number; name: string } | null
  therapist: AppointmentParty | null
}
export interface MobileFeedback {
  id: number
  rating: number
  comment: string | null
  sentiment: 'positive' | 'neutral' | 'negative'
  submitted_at: string | null
  service: { id: number; name: string } | null
  appointment: { id: number; appointment_number: string; completed_at: string | null } | null
}
export interface FeedbackListResponse {
  data: MobileFeedback[]
  eligible_appointments: EligibleFeedbackAppointment[]
  summary: { awaiting_feedback: number; submitted: number }
  meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null }
}
export interface CustomerProfileData {
  name: string
  email: string
  phone: string | null
  address: string | null
  contact_preference: string | null
  customer_code: string
  has_password: boolean
  google_linked: boolean
  contact_preferences: Array<{ value: string; label: string }>
}
export interface OperationalAppointment {
  id: number
  appointment_number: string
  status: 'confirmed' | 'completed' | 'cancelled' | 'no_show'
  starts_at: string | null
  ends_at: string | null
  customer_notes: string | null
  internal_notes: string | null
  customer: { id: number; customer_code: string; name: string | null; phone: string | null } | null
  service: { id: number; name: string; duration_minutes: number; price: string } | null
  therapist: AppointmentParty | null
  preferred_therapist: AppointmentParty | null
  addons: Array<{ code: string; name: string; price: string; duration_minutes: number }>
  expected_amount: string
  transaction: { id: number; transaction_number: string; amount: string; payment_status: string } | null
  actions: { can_edit: boolean; can_cancel: boolean; can_mark_no_show: boolean; can_finish: boolean }
}
export interface ReceptionAppointmentOptions {
  customers: Array<{ id: number; customer_code: string; name: string | null; phone: string | null }>
  services: Array<{ id: number; name: string; duration_minutes: number; price: string }>
  addons: BookingAddon[]
  payment_statuses: string[]
  payment_methods: string[]
  default_payment_method: string
  initial_start_at: string
}
export interface OperationalTransaction {
  id: number
  transaction_number: string
  amount: string
  payment_status: string
  payment_method: string | null
  paid_at: string | null
  notes: string | null
  customer: { id: number; customer_code: string; name: string | null } | null
  service: { id: number; name: string } | null
  appointment: { id: number; appointment_number: string } | null
  recorded_by: string | null
}
export interface ReceptionCustomerSummary { id: number; customer_code: string; name: string | null; phone: string | null; appointments_count: number; transactions_count: number }
export interface ReceptionCustomerDetail extends ReceptionCustomerSummary {
  email: string | null
  address: string | null
  contact_preference: string | null
  notes: string | null
  contact_preferences: Array<{ value: string; label: string }>
  appointments: Array<{ id: number; appointment_number: string; status: string; starts_at: string | null; service: string | null; therapist: string | null }>
  transactions: Array<{ id: number; transaction_number: string; amount: string; payment_status: string; service: string | null; paid_at: string | null }>
  feedback: Array<{ id: number; rating: number; comment: string | null; sentiment: string; service: string | null; submitted_at: string | null }>
}
export interface ReceptionTransactionOptions {
  customers: Array<{ id: number; customer_code: string; name: string | null }>
  services: Array<{ id: number; name: string }>
  appointments: Array<{ id: number; appointment_number: string; customer_profile_id: number; service_id: number; customer_name: string | null; service_name: string | null; expected_amount: string }>
  payment_statuses: string[]
  payment_methods: string[]
  default_payment_method: string
}
export interface StaffDashboard {
  profile: { id: number; name: string; specialization: string | null }
  summary: { assigned_today: number; upcoming: number; completed_today: number; feedback: number }
  commissions: { pending: string; paid: string; net: string }
  today_appointments: OperationalAppointment[]
}
export interface StaffCustomerSummary { id: number; customer_code: string; name: string | null; phone: string | null; assigned_appointments_count: number }
export interface StaffCustomerDetail extends StaffCustomerSummary {
  email: string | null; address: string | null; contact_preference: string | null; notes: string | null
  appointments: Array<{ id: number; appointment_number: string; status: string; starts_at: string | null; service: string | null; transaction: { amount: string; payment_status: string } | null; feedback: { rating: number; comment: string | null; sentiment: string } | null }>
}
export interface StaffFeedback { id: number; rating: number; comment: string | null; sentiment: 'positive' | 'neutral' | 'negative'; submitted_at: string | null; customer: AppointmentParty | null; service: AppointmentParty | null; appointment: { id: number; appointment_number: string } | null }
export interface StaffCommission { id: number; type: 'earning' | 'adjustment'; status: 'pending' | 'paid'; basis_amount: string; rate: string; amount: string; earned_at: string | null; paid_at: string | null; notes: string | null; appointment: { id: number; appointment_number: string; service: string | null } | null; transaction: { id: number; transaction_number: string } | null }
export interface AdminDashboard {
  summary: { today: number; upcoming: number; payments_today: string; today_appointments: number; upcoming_appointments: number; today_revenue: string; new_feedback: number; available_rewards: number; customers: number; active_services: number; bookable_therapists: number }
  today_appointments: OperationalAppointment[]
  upcoming_appointments: OperationalAppointment[]
  is_super_admin: boolean
}
export interface AdminService { id: number; name: string; slug: string; description: string | null; duration_minutes: number; price: string; is_active: boolean; staff_count: number; appointments_count: number; transactions_count: number }
export interface AdminStaffSummary { id: number; name: string | null; email: string | null; phone: string | null; is_active: boolean; staff_type: string; position: string | null; specialization: string | null; is_bookable: boolean; services: Array<{ id: number; name: string }>; services_count: number; appointments_count: number }
export interface AdminStaffDetail extends AdminStaffSummary { bio: string | null; hire_date: string | null; weekly_schedules: Array<{ id: number; day_of_week: number; start_time: string; end_time: string; ends_next_day: boolean; is_available: boolean }>; schedule_exceptions: Array<{ id: number; exception_date: string; exception_type: string; start_time: string | null; end_time: string | null; ends_next_day: boolean; reason: string | null }> }
export interface AdminStaffOptions { can_create: boolean; staff_types: string[]; services: Array<{ id: number; name: string }> }
export interface AdminCommission extends StaffCommission { therapist: AppointmentParty | null }
export interface AdminPromotion { id: number; customer: AppointmentParty | null; group: string | null; recency_days: number; frequency_count: number; monetary_total: string; reward: string; status: string; expires_at: string | null; can_dismiss: boolean }
export interface AdminPromotionPreset { key: string; name: string; description?: string; addon_code: string | null; is_active: boolean }
export interface AdminSettings { settings: { business_name: string; contact_email: string | null; contact_phone: string | null; business_address: string | null; default_payment_method: string }; payment_methods: string[]; operating: { timezone: string; opens_at: string; closes_at: string; slot_interval_minutes: number; commission_rate: string }; security: Array<{ label: string; ready: boolean; value: string }> }
export interface AdminUserAccess { id: number; name: string; email: string; role: string; is_active: boolean; google_linked: boolean; email_verified: boolean; protected: boolean; staff_profile_id: number | null; customer_profile_id: number | null }
export interface AdminReport { type: string; types: string[]; columns: string[]; data: Array<Array<string | number | null>>; summary: { appointments: number; revenue: string; customers: number; feedback: number }; meta: AppointmentListResponse['meta'] }
export interface AdminRoster { schedule_week_id: number; week_start: string; week_end: string; published_at: string | null; has_draft: boolean; resources: Array<{ id: number; name: string; subtitle: string }>; draft_shifts: Array<{ id: number; staff_profile_id: number; schedule_date: string; start_time: string; end_time: string; ends_next_day: boolean }>; published_shifts: Array<{ id: number; staff_profile_id: number; schedule_date: string; start_time: string; end_time: string; ends_next_day: boolean }> }

let client: AxiosInstance | null = null
let token = ''

export function configureApi(baseUrl: string): void {
  client = axios.create({ baseURL: `${baseUrl}/api/v1`, headers: { Accept: 'application/json' }, timeout: baseUrl === BACKEND_URL ? 75_000 : 10_000, withCredentials: false })
  client.interceptors.request.use((config) => {
    if (token) config.headers.Authorization = `Bearer ${token}`
    return config
  })
}

export function setToken(value: string): void { token = value }
export function apiError(reason: unknown): ApiError {
  if (axios.isAxiosError(reason)) return reason.response?.data?.error ?? { code: 'NETWORK_ERROR', message: 'The Casa Paraiso server could not be reached.' }
  return { code: 'UNKNOWN_ERROR', message: 'Something went wrong. Please try again.' }
}
function getClient(): AxiosInstance { if (!client) throw new Error('Pair this phone before signing in.'); return client }

export async function login(payload: { email: string; password: string; device_id: string; device_name: string }): Promise<{ token: string; expires_at: string; user: MobileUser }> {
  return (await getClient().post('/auth/login', payload)).data.data
}
export async function exchangeGoogle(payload: { instance_id: string; device_id: string; device_name: string; code: string; code_verifier: string }): Promise<{ token: string; expires_at: string; user: MobileUser }> {
  return (await getClient().post('/auth/google/exchange', payload)).data.data
}
export async function me(): Promise<MobileUser> { return (await getClient().get('/auth/me')).data.data }
export async function logout(): Promise<void> { await getClient().post('/auth/logout') }
export async function customerAppointments(params: { page?: number; status?: string } = {}): Promise<AppointmentListResponse> {
  return (await getClient().get('/customer/appointments', { params })).data
}
export async function cancelCustomerAppointment(id: number): Promise<{ data: MobileAppointment; message: string }> {
  return (await getClient().patch(`/customer/appointments/${id}/cancel`)).data
}
export async function customerBookingOptions(): Promise<BookingOptions> {
  return (await getClient().get('/customer/booking-options')).data.data
}
export async function customerAvailability(params: {
  service_id: number
  preferred_staff_profile_id?: number
  promotion_suggestion_id?: number
  addon_codes?: string[]
  month: string
}): Promise<BookingAvailability> {
  return (await getClient().get('/customer/availability', { params })).data.data
}
export async function createCustomerAppointment(payload: {
  service_id: number
  preferred_staff_profile_id?: number
  promotion_suggestion_id?: number
  addon_codes?: string[]
  requested_start_at: string
  customer_notes?: string
}): Promise<{ data: MobileAppointment; message: string }> {
  return (await getClient().post('/customer/appointments', payload)).data
}
export async function customerFeedback(page = 1): Promise<FeedbackListResponse> {
  return (await getClient().get('/customer/feedback', { params: { page } })).data
}
export async function submitCustomerFeedback(payload: { appointment_id: number; rating: number; comment?: string }): Promise<{ data: MobileFeedback; message: string }> {
  return (await getClient().post('/customer/feedback', payload)).data
}
export async function customerProfile(): Promise<CustomerProfileData> {
  return (await getClient().get('/customer/profile')).data.data
}
export async function updateCustomerProfile(payload: { name: string; phone?: string; address?: string; contact_preference?: string }): Promise<{ data: CustomerProfileData; message: string }> {
  return (await getClient().patch('/customer/profile', payload)).data
}
export async function updatePassword(payload: { current_password: string; password: string; password_confirmation: string }): Promise<{ message: string }> {
  return (await getClient().patch('/auth/password', payload)).data
}
export async function receptionDashboard(prefix: 'reception' | 'admin' = 'reception'): Promise<{ summary: { today: number; upcoming: number; customers: number; payments_today: string }; today_appointments: OperationalAppointment[] }> {
  return (await getClient().get(`/${prefix}/dashboard`)).data.data
}
export async function receptionAppointments(params: { page?: number; status?: string; date?: string; q?: string } = {}, prefix: 'reception' | 'admin' = 'reception') {
  return (await getClient().get(`/${prefix}/appointments`, { params })).data as { data: OperationalAppointment[]; summary: { confirmed: number; completed: number; cancelled: number }; meta: AppointmentListResponse['meta'] }
}
export async function receptionAppointmentOptions(prefix: 'reception' | 'admin' = 'reception'): Promise<ReceptionAppointmentOptions> { return (await getClient().get(prefix === 'admin' ? '/admin/appointments/options' : '/reception/appointment-options')).data.data }
export async function receptionAvailableTherapists(params: { service_id: number; starts_at: string; appointment_id?: number; addon_codes?: string[] }, prefix: 'reception' | 'admin' = 'reception'): Promise<BookingTherapist[]> { return (await getClient().get(prefix === 'admin' ? '/admin/appointments/available-therapists' : '/reception/available-therapists', { params })).data.data }
export async function createReceptionAppointment(payload: Record<string, unknown>, prefix: 'reception' | 'admin' = 'reception') { return (await getClient().post(`/${prefix}/appointments`, payload)).data as { data: OperationalAppointment; message: string } }
export async function updateReceptionAppointment(id: number, payload: Record<string, unknown>, prefix: 'reception' | 'admin' = 'reception') { return (await getClient().patch(`/${prefix}/appointments/${id}`, payload)).data as { data: OperationalAppointment; message: string } }
export async function setReceptionAppointmentOutcome(id: number, status: 'cancelled' | 'no_show', reason?: string, prefix: 'reception' | 'admin' = 'reception') { return (await getClient().post(`/${prefix}/appointments/${id}/outcome`, { status, reason })).data as { data: OperationalAppointment; message: string } }
export async function completeReceptionAppointment(id: number, payload: Record<string, unknown>, prefix: 'reception' | 'admin' = 'reception') { return (await getClient().post(`/${prefix}/appointments/${id}/complete`, payload)).data as { data: OperationalTransaction; message: string } }
export async function receptionCustomers(params: { page?: number; q?: string } = {}, prefix: 'reception' | 'admin' = 'reception') { return (await getClient().get(`/${prefix}/customers`, { params })).data as { data: ReceptionCustomerSummary[]; meta: AppointmentListResponse['meta'] } }
export async function receptionCustomer(id: number, prefix: 'reception' | 'admin' = 'reception'): Promise<ReceptionCustomerDetail> { return (await getClient().get(`/${prefix}/customers/${id}`)).data.data }
export async function updateReceptionCustomer(id: number, payload: Record<string, unknown>, prefix: 'reception' | 'admin' = 'reception') { return (await getClient().patch(`/${prefix}/customers/${id}`, payload)).data as { data: ReceptionCustomerSummary; message: string } }
export async function receptionTransactions(params: { page?: number; payment_status?: string; q?: string } = {}, prefix: 'reception' | 'admin' = 'reception') { return (await getClient().get(`/${prefix}/transactions`, { params })).data as { data: OperationalTransaction[]; summary: { paid: string; unpaid_count: number; partial_count: number }; meta: AppointmentListResponse['meta'] } }
export async function receptionTransactionOptions(prefix: 'reception' | 'admin' = 'reception'): Promise<ReceptionTransactionOptions> { return (await getClient().get(prefix === 'admin' ? '/admin/transactions/options' : '/reception/transaction-options')).data.data }
export async function createReceptionTransaction(payload: Record<string, unknown>, prefix: 'reception' | 'admin' = 'reception') { return (await getClient().post(`/${prefix}/transactions`, payload)).data as { data: OperationalTransaction; message: string } }
export async function updateReceptionTransaction(id: number, payload: Record<string, unknown>, prefix: 'reception' | 'admin' = 'reception') { return (await getClient().patch(`/${prefix}/transactions/${id}`, payload)).data as { data: OperationalTransaction; message: string } }

export async function adminDashboard(): Promise<AdminDashboard> { return (await getClient().get('/admin/dashboard')).data.data }
export async function adminServices(params: { page?: number; q?: string; status?: string } = {}) { return (await getClient().get('/admin/services', { params })).data as { data: AdminService[]; summary: { active: number; inactive: number }; meta: AppointmentListResponse['meta'] } }
export async function createAdminService(payload: Record<string, unknown>) { return (await getClient().post('/admin/services', payload)).data as { data: AdminService; message: string } }
export async function updateAdminService(id: number, payload: Record<string, unknown>) { return (await getClient().patch(`/admin/services/${id}`, payload)).data as { data: AdminService; message: string } }
export async function toggleAdminService(id: number) { return (await getClient().patch(`/admin/services/${id}/toggle`)).data as { data: AdminService; message: string } }
export async function adminStaff(params: { page?: number; q?: string; status?: string; bookable?: string } = {}) { return (await getClient().get('/admin/staff', { params })).data as { data: AdminStaffSummary[]; summary: { active: number; inactive: number; bookable: number }; meta: AppointmentListResponse['meta'] } }
export async function adminStaffOptions(): Promise<AdminStaffOptions> { return (await getClient().get('/admin/staff/options')).data.data }
export async function adminStaffDetail(id: number): Promise<AdminStaffDetail> { return (await getClient().get(`/admin/staff/${id}`)).data.data }
export async function createAdminStaff(payload: Record<string, unknown>) { return (await getClient().post('/admin/staff', payload)).data as { data: AdminStaffDetail; message: string } }
export async function updateAdminStaff(id: number, payload: Record<string, unknown>) { return (await getClient().patch(`/admin/staff/${id}`, payload)).data as { data: AdminStaffDetail; message: string } }
export async function createAdminWeeklySchedule(staffId: number, payload: Record<string, unknown>) { return (await getClient().post(`/admin/staff/${staffId}/weekly-schedules`, payload)).data as { data: AdminStaffDetail['weekly_schedules'][number]; message: string } }
export async function deleteAdminWeeklySchedule(staffId: number, id: number) { return (await getClient().delete(`/admin/staff/${staffId}/weekly-schedules/${id}`)).data as { message: string } }
export async function createAdminScheduleException(staffId: number, payload: Record<string, unknown>) { return (await getClient().post(`/admin/staff/${staffId}/schedule-exceptions`, payload)).data as { data: AdminStaffDetail['schedule_exceptions'][number]; message: string } }
export async function deleteAdminScheduleException(staffId: number, id: number) { return (await getClient().delete(`/admin/staff/${staffId}/schedule-exceptions/${id}`)).data as { message: string } }
export async function adminRoster(week: string): Promise<AdminRoster> { return (await getClient().get('/admin/staff-roster', { params: { week } })).data }
export async function copyAdminRoster(week: string): Promise<AdminRoster> { return (await getClient().post('/admin/staff-roster/copy', { week })).data }
export async function addAdminRosterShift(weekId: number, payload: Record<string, unknown>): Promise<AdminRoster> { return (await getClient().post(`/admin/staff-roster/${weekId}/shifts`, payload)).data }
export async function deleteAdminRosterShift(weekId: number, shiftId: number): Promise<AdminRoster> { return (await getClient().delete(`/admin/staff-roster/${weekId}/shifts/${shiftId}`)).data }
export async function publishAdminRoster(weekId: number): Promise<AdminRoster> { return (await getClient().post(`/admin/staff-roster/${weekId}/publish`)).data }
export async function adminFeedback(params: { page?: number; sentiment?: string; q?: string } = {}) { return (await getClient().get('/admin/feedback', { params })).data as { data: StaffFeedback[]; summary: { positive: number; neutral: number; negative: number }; meta: AppointmentListResponse['meta'] } }
export async function adminCommissions(params: { page?: number; status?: string; staff_profile_id?: number; date_from?: string; date_to?: string } = {}) { return (await getClient().get('/admin/commissions', { params })).data as { data: AdminCommission[]; summary: { pending: string; paid: string; net: string }; staff: AppointmentParty[]; meta: AppointmentListResponse['meta'] } }
export async function payAdminCommission(id: number, payload: { paid_at: string; notes?: string }) { return (await getClient().patch(`/admin/commissions/${id}/pay`, payload)).data as { data: AdminCommission; message: string } }
export async function adminPromotions(params: { page?: number; lifecycle?: string; q?: string } = {}) { return (await getClient().get('/admin/promotions', { params })).data as { data: AdminPromotion[]; summary: { available: number; reserved: number; used: number; expired: number; dismissed: number }; settings: { promotion_voucher_validity_days: number | null; validity_options: number[] }; presets: AdminPromotionPreset[]; addons: Array<{ code: string; name: string }>; meta: AppointmentListResponse['meta'] } }
export async function dismissAdminPromotion(id: number) { return (await getClient().patch(`/admin/promotions/${id}/dismiss`)).data as { message: string } }
export async function updateAdminPromotionSettings(payload: Record<string, unknown>) { return (await getClient().patch('/admin/promotions/settings', payload)).data as { message: string } }
export async function adminSettings(): Promise<AdminSettings> { return (await getClient().get('/admin/settings')).data.data }
export async function updateAdminSettings(payload: Record<string, unknown>) { return (await getClient().patch('/admin/settings', payload)).data as { message: string } }
export async function adminUsers(page = 1) { return (await getClient().get('/admin/users', { params: { page } })).data as { data: AdminUserAccess[]; roles: string[]; meta: AppointmentListResponse['meta'] } }
export async function createAdminUser(payload: Record<string, unknown>) { return (await getClient().post('/admin/users', payload)).data as { data: AdminUserAccess; message: string } }
export async function updateAdminUser(id: number, payload: Record<string, unknown>) { return (await getClient().patch(`/admin/users/${id}`, payload)).data as { data: AdminUserAccess; message: string } }
export async function adminReports(params: Record<string, string | number | undefined> = {}): Promise<AdminReport> { return (await getClient().get('/admin/reports', { params })).data }
export async function exportAdminReport(params: Record<string, string | undefined> = {}): Promise<Blob> { return (await getClient().get('/admin/reports/export', { params, responseType: 'blob' })).data }
export async function staffDashboard(): Promise<StaffDashboard> { return (await getClient().get('/staff/dashboard')).data.data }
export async function staffAppointments(params: { page?: number; status?: string; date?: string; q?: string } = {}) { return (await getClient().get('/staff/appointments', { params })).data as { data: OperationalAppointment[]; summary: { confirmed: number; completed: number; no_show: number }; meta: AppointmentListResponse['meta'] } }
export async function setStaffAppointmentNoShow(id: number, reason?: string) { return (await getClient().post(`/staff/appointments/${id}/outcome`, { status: 'no_show', reason })).data as { data: OperationalAppointment; message: string } }
export async function completeStaffAppointment(id: number, payload: Record<string, unknown>) { return (await getClient().post(`/staff/appointments/${id}/complete`, payload)).data as { data: OperationalTransaction; message: string } }
export async function staffCustomers(params: { page?: number; q?: string } = {}) { return (await getClient().get('/staff/customers', { params })).data as { data: StaffCustomerSummary[]; meta: AppointmentListResponse['meta'] } }
export async function staffCustomer(id: number): Promise<StaffCustomerDetail> { return (await getClient().get(`/staff/customers/${id}`)).data.data }
export async function staffTransactions(params: { page?: number; payment_status?: string; q?: string } = {}) { return (await getClient().get('/staff/transactions', { params })).data as { data: OperationalTransaction[]; summary: { paid: string; unpaid_count: number; partial_count: number }; meta: AppointmentListResponse['meta'] } }
export async function staffFeedback(params: { page?: number; sentiment?: string; q?: string } = {}) { return (await getClient().get('/staff/feedback', { params })).data as { data: StaffFeedback[]; meta: AppointmentListResponse['meta'] } }
export async function staffCommissions(params: { page?: number; status?: string } = {}) { return (await getClient().get('/staff/commissions', { params })).data as { data: StaffCommission[]; summary: { pending: string; paid: string; net: string }; meta: AppointmentListResponse['meta'] } }
