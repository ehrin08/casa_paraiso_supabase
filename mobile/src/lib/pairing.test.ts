import { describe, expect, it } from 'vitest'
import { normalizeBackendUrl, parsePairingDeepLink, validateMeta } from './pairing'

describe('mobile pairing validation', () => {
  it('accepts a Cloudflare Quick Tunnel or APK download link', () => {
    expect(normalizeBackendUrl('https://quiet-lotus-123.trycloudflare.com/')).toBe('https://quiet-lotus-123.trycloudflare.com')
    expect(normalizeBackendUrl('https://quiet-lotus-123.trycloudflare.com/api/v1/demo/Casa-Paraiso-Mobile.apk')).toBe('https://quiet-lotus-123.trycloudflare.com')
    expect(() => normalizeBackendUrl('http://quiet-lotus-123.trycloudflare.com')).toThrow()
    expect(() => normalizeBackendUrl('https://example.com')).toThrow()
    expect(() => normalizeBackendUrl('https://quiet-lotus-123.trycloudflare.com/api')).toThrow()
  })

  it('parses only a well-formed pairing deep link', () => {
    expect(parsePairingDeepLink('casaparaiso://pair?url=https%3A%2F%2Fquiet-lotus-123.trycloudflare.com')).toEqual({
      url: 'https://quiet-lotus-123.trycloudflare.com',
    })
    expect(parsePairingDeepLink('casaparaiso://pair?url=https%3A%2F%2Fevil.example')).toBeNull()
  })

  it('rejects a metadata identity mismatch', () => {
    expect(() => validateMeta({ data: {
      service: 'unexpected', api_version: 'v1', instance_id: 'bda2fdb4-c8a4-4e0d-bc75-43ccd6b23811', timezone: 'Asia/Manila', server_time: new Date().toISOString(), supported_auth: [], pairing: { protocol: 2, enabled: true },
    }})).toThrow()
  })
})
