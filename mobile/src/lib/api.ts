import axios, { type AxiosInstance } from 'axios'

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

let client: AxiosInstance | null = null
let token = ''

export function configureApi(baseUrl: string): void {
  client = axios.create({ baseURL: `${baseUrl}/api/v1`, headers: { Accept: 'application/json' }, timeout: 10_000, withCredentials: false })
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
export async function receptionDashboard(): Promise<{ summary: { today: number; upcoming: number; customers: number; payments_today: string }; today_appointments: OperationalAppointment[] }> {
  return (await getClient().get('/reception/dashboard')).data.data
}
export async function receptionAppointments(params: { page?: number; status?: string; date?: string; q?: string } = {}) {
  return (await getClient().get('/reception/appointments', { params })).data as { data: OperationalAppointment[]; summary: { confirmed: number; completed: number; cancelled: number }; meta: AppointmentListResponse['meta'] }
}
export async function receptionAppointmentOptions(): Promise<ReceptionAppointmentOptions> { return (await getClient().get('/reception/appointment-options')).data.data }
export async function receptionAvailableTherapists(params: { service_id: number; starts_at: string; appointment_id?: number; addon_codes?: string[] }): Promise<BookingTherapist[]> { return (await getClient().get('/reception/available-therapists', { params })).data.data }
export async function createReceptionAppointment(payload: Record<string, unknown>) { return (await getClient().post('/reception/appointments', payload)).data as { data: OperationalAppointment; message: string } }
export async function updateReceptionAppointment(id: number, payload: Record<string, unknown>) { return (await getClient().patch(`/reception/appointments/${id}`, payload)).data as { data: OperationalAppointment; message: string } }
export async function setReceptionAppointmentOutcome(id: number, status: 'cancelled' | 'no_show', reason?: string) { return (await getClient().post(`/reception/appointments/${id}/outcome`, { status, reason })).data as { data: OperationalAppointment; message: string } }
export async function completeReceptionAppointment(id: number, payload: Record<string, unknown>) { return (await getClient().post(`/reception/appointments/${id}/complete`, payload)).data as { data: OperationalTransaction; message: string } }
export async function receptionCustomers(params: { page?: number; q?: string } = {}) { return (await getClient().get('/reception/customers', { params })).data as { data: ReceptionCustomerSummary[]; meta: AppointmentListResponse['meta'] } }
export async function receptionCustomer(id: number): Promise<ReceptionCustomerDetail> { return (await getClient().get(`/reception/customers/${id}`)).data.data }
export async function updateReceptionCustomer(id: number, payload: Record<string, unknown>) { return (await getClient().patch(`/reception/customers/${id}`, payload)).data as { data: ReceptionCustomerSummary; message: string } }
export async function receptionTransactions(params: { page?: number; payment_status?: string; q?: string } = {}) { return (await getClient().get('/reception/transactions', { params })).data as { data: OperationalTransaction[]; summary: { paid: string; unpaid_count: number; partial_count: number }; meta: AppointmentListResponse['meta'] } }
export async function receptionTransactionOptions(): Promise<ReceptionTransactionOptions> { return (await getClient().get('/reception/transaction-options')).data.data }
export async function createReceptionTransaction(payload: Record<string, unknown>) { return (await getClient().post('/reception/transactions', payload)).data as { data: OperationalTransaction; message: string } }
export async function updateReceptionTransaction(id: number, payload: Record<string, unknown>) { return (await getClient().patch(`/reception/transactions/${id}`, payload)).data as { data: OperationalTransaction; message: string } }
