import axios, { type AxiosInstance } from 'axios'

export interface MobileUser { id: number; name: string; email: string; phone: string | null; role: string; workspace: 'admin' | 'reception' | 'staff' | 'customer'; email_verified: boolean }
export interface ApiError { code: string; message: string; fields?: Record<string, string[]> }

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
