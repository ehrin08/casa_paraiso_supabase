import axios from 'axios'

export const PAIRING_PROTOCOL = 2
export const SERVICE_IDENTITY = 'casa-paraiso-mobile-api'

export const DEFAULT_BACKEND_URL = 'https://casa-paraiso-supabase-api-poc.onrender.com'

export function normalizeConfiguredBackendUrl(value: string): string {
  if (!value.trim()) throw new Error('The Casa Paraiso backend URL is not configured.')
  const url = new URL(value.trim())
  if (url.protocol !== 'https:' || url.username || url.password || url.port || url.search || url.hash || (url.pathname !== '/' && url.pathname !== '')) {
    throw new Error('The Casa Paraiso backend URL must be an HTTPS origin.')
  }
  return url.origin
}

export const BACKEND_URL = normalizeConfiguredBackendUrl(import.meta.env.VITE_BACKEND_URL ?? DEFAULT_BACKEND_URL)

export interface MetaResponse {
  data: {
    service: string
    api_version: string
    instance_id: string
    timezone: string
    server_time: string
    supported_auth: string[]
    pairing: { protocol: number; enabled: boolean }
  }
}

export function normalizeBackendUrl(value: string): string {
  const url = new URL(value.trim())
  return normalizeConfiguredBackendUrl(url.origin)
}

export function validateMeta(meta: MetaResponse, expectedInstanceId?: string): void {
  const data = meta.data
  if (data.service !== SERVICE_IDENTITY || data.api_version !== 'v1' || data.pairing.protocol !== PAIRING_PROTOCOL) {
    throw new Error('This server is not compatible with the Casa Paraiso mobile app.')
  }
  if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(data.instance_id)) {
    throw new Error('The server returned an invalid pairing identity.')
  }
  if (data.timezone !== 'Asia/Manila' || Number.isNaN(Date.parse(data.server_time))) {
    throw new Error('The server metadata is incomplete.')
  }
  if (expectedInstanceId && data.instance_id !== expectedInstanceId) {
    throw new Error('This server belongs to a different Casa Paraiso instance. Re-pair to continue.')
  }
}

export async function fetchMeta(baseUrl: string): Promise<MetaResponse> {
  const response = await axios.get<MetaResponse>(`${baseUrl}/api/v1/meta`, {
    headers: { Accept: 'application/json' },
    timeout: 90_000,
    withCredentials: false,
    maxRedirects: 0,
  })
  validateMeta(response.data)
  return response.data
}
