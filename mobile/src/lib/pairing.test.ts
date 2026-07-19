import { describe, expect, it } from 'vitest'
import { BACKEND_URL, DEFAULT_BACKEND_URL, normalizeBackendUrl, normalizeConfiguredBackendUrl, validateMeta } from './pairing'

describe('fixed mobile backend configuration', () => {
  it('uses the stable Render endpoint by default', () => {
    expect(DEFAULT_BACKEND_URL).toBe('https://casa-paraiso-supabase-api-poc.onrender.com')
    expect(BACKEND_URL).toBe(DEFAULT_BACKEND_URL)
  })

  it('accepts only HTTPS origins', () => {
    expect(normalizeBackendUrl('https://casa-paraiso-supabase-api-poc.onrender.com/')).toBe(DEFAULT_BACKEND_URL)
    expect(() => normalizeConfiguredBackendUrl('http://casa-paraiso-supabase-api-poc.onrender.com')).toThrow()
    expect(() => normalizeConfiguredBackendUrl('https://casa-paraiso-supabase-api-poc.onrender.com/api')).toThrow()
    expect(() => normalizeConfiguredBackendUrl('')).toThrow()
  })

  it('keeps metadata validation independent of user pairing', () => {
    expect(() => validateMeta({ data: {
      service: 'unexpected', api_version: 'v1', instance_id: 'bda2fdb4-c8a4-4e0d-bc75-43ccd6b23811', timezone: 'Asia/Manila', server_time: new Date().toISOString(), supported_auth: [], pairing: { protocol: 2, enabled: true },
    }})).toThrow()
  })
})
