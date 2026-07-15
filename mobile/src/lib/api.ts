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
