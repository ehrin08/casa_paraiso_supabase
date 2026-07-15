import axios from 'axios'

export const PAIRING_PROTOCOL = 1
export const SERVICE_IDENTITY = 'casa-paraiso-mobile-api'

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

export interface PairingReceipt {
  data: { instance_id: string; pairing_protocol: number; paired_at: string }
}

export function normalizeBackendUrl(value: string): string {
  const url = new URL(value.trim())
  if (url.protocol !== 'https:' || !/^[a-z0-9-]+\.trycloudflare\.com$/.test(url.hostname)) {
    throw new Error('Use the HTTPS Quick Tunnel address shown by the demo helper.')
  }
  if (url.username || url.password || url.port || url.pathname !== '/' || url.search || url.hash) {
    throw new Error('Use only the tunnel address, without a path, port, or extra text.')
  }
  return url.origin
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
    throw new Error('This tunnel belongs to a different Casa Paraiso instance. Re-pair to continue.')
  }
}

export function parsePairingDeepLink(value: string): { url: string; code: string } | null {
  try {
    const link = new URL(value)
    if (link.protocol !== 'casaparaiso:' || link.hostname !== 'pair') return null
    const url = normalizeBackendUrl(link.searchParams.get('url') ?? '')
    const code = link.searchParams.get('code') ?? ''
    if (!/^\d{8}$/.test(code)) return null
    return { url, code }
  } catch {
    return null
  }
}

export async function fetchMeta(baseUrl: string): Promise<MetaResponse> {
  const response = await axios.get<MetaResponse>(`${baseUrl}/api/v1/meta`, {
    headers: { Accept: 'application/json' },
    timeout: 10_000,
    withCredentials: false,
    maxRedirects: 0,
  })
  validateMeta(response.data)
  return response.data
}

export async function verifyPairing(baseUrl: string, instanceId: string, code: string): Promise<PairingReceipt> {
  const response = await axios.post<PairingReceipt>(`${baseUrl}/api/v1/pairings/verify`, {
    instance_id: instanceId,
    code,
  }, {
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    timeout: 10_000,
    withCredentials: false,
  })
  if (response.data.data.instance_id !== instanceId || response.data.data.pairing_protocol !== PAIRING_PROTOCOL) {
    throw new Error('The server returned an invalid pairing receipt.')
  }
  return response.data
}
