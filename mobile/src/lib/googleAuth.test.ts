import { describe, expect, it } from 'vitest'
import { buildGoogleAuthorizationUrl, isGoogleCallback, parseGoogleCallback, type GooglePending } from './googleAuth'

const pending: GooglePending = {
  state: 'c3RhdGUtc3RhdGUtc3RhdGUtc3RhdGUtc3RhdGUtc3Q',
  verifier: 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-._~',
  instanceId: 'bda2fdb4-c8a4-4e0d-bc75-43ccd6b23811',
  expiresAt: 10_000,
}

describe('mobile Google OAuth helpers', () => {
  it('builds a device-bound authorization URL without the verifier', () => {
    const value = buildGoogleAuthorizationUrl(
      'https://quiet-lotus-123.trycloudflare.com', pending,
      'Y2hhbGxlbmdlLWNoYWxsZW5nZS1jaGFsbGVuZ2UtY2g',
      'f688534b-9b27-4ca5-b879-cd52eac79ca9', 'Casa Paraiso Android',
    )
    const url = new URL(value)
    expect(url.pathname).toBe('/api/v1/auth/google/redirect')
    expect(url.searchParams.get('state')).toBe(pending.state)
    expect(url.searchParams.has('code_verifier')).toBe(false)
  })

  it('accepts only the matching unexpired app callback', () => {
    const code = 'ZXhjaGFuZ2UtZXhjaGFuZ2UtZXhjaGFuZ2UtZXhjaGE'
    expect(parseGoogleCallback(`casaparaiso://oauth/callback?state=${pending.state}&code=${code}`, pending, 9_000)).toEqual({
      code,
      verifier: pending.verifier,
    })
    expect(() => parseGoogleCallback(`casaparaiso://oauth/callback?state=wrong&code=${code}`, pending, 9_000)).toThrow()
    expect(() => parseGoogleCallback(`casaparaiso://oauth/callback?state=${pending.state}&code=${code}`, pending, 10_000)).toThrow()
  })

  it('recognizes only the OAuth callback deep link', () => {
    expect(isGoogleCallback(`casaparaiso://oauth/callback?state=${pending.state}`)).toBe(true)
    expect(isGoogleCallback('casaparaiso://pair?code=12345678')).toBe(false)
  })
})
