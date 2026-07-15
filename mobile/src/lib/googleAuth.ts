import { SecureStorage } from '@aparajita/capacitor-secure-storage'
import { Capacitor } from '@capacitor/core'

const KEY = 'casa.mobile.google-oauth'
const TTL_MS = 5 * 60 * 1000
let browserPending: string | null = null

export interface GooglePending {
  state: string
  verifier: string
  instanceId: string
  expiresAt: number
}

export interface GoogleCallback {
  code: string
  verifier: string
}

function base64Url(bytes: Uint8Array): string {
  let binary = ''
  for (const byte of bytes) binary += String.fromCharCode(byte)
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

export async function createPkce(): Promise<{ state: string; verifier: string; challenge: string }> {
  const stateBytes = crypto.getRandomValues(new Uint8Array(32))
  const verifierBytes = crypto.getRandomValues(new Uint8Array(48))
  const state = base64Url(stateBytes)
  const verifier = base64Url(verifierBytes)
  const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier))
  return { state, verifier, challenge: base64Url(new Uint8Array(digest)) }
}

export function buildGoogleAuthorizationUrl(
  baseUrl: string,
  pending: GooglePending,
  challenge: string,
  deviceId: string,
  deviceName: string,
): string {
  const url = new URL('/api/v1/auth/google/redirect', baseUrl)
  url.searchParams.set('instance_id', pending.instanceId)
  url.searchParams.set('device_id', deviceId)
  url.searchParams.set('device_name', deviceName)
  url.searchParams.set('state', pending.state)
  url.searchParams.set('code_challenge', challenge)
  return url.toString()
}

export function parseGoogleCallback(value: string, pending: GooglePending, now = Date.now()): GoogleCallback {
  const url = new URL(value)
  if (url.protocol !== 'casaparaiso:' || url.hostname !== 'oauth' || url.pathname !== '/callback') {
    throw new Error('This is not a Casa Paraiso Google callback.')
  }
  if (pending.expiresAt <= now || url.searchParams.get('state') !== pending.state) {
    throw new Error('This Google sign-in request expired or belongs to another phone.')
  }

  const error = url.searchParams.get('error')
  if (error === 'google_cancelled') throw new Error('Google sign-in was cancelled.')
  if (error === 'account_ineligible') throw new Error('This Google account cannot sign in to Casa Paraiso.')
  if (error) throw new Error('Google sign-in could not be completed. Please try again.')

  const code = url.searchParams.get('code') ?? ''
  if (!/^[A-Za-z0-9_-]{43}$/.test(code)) throw new Error('The Google exchange code is invalid.')
  return { code, verifier: pending.verifier }
}

export function isGoogleCallback(value: string): boolean {
  try {
    const url = new URL(value)
    return url.protocol === 'casaparaiso:' && url.hostname === 'oauth' && url.pathname === '/callback'
  } catch { return false }
}

export async function beginGoogleAuthorization(
  baseUrl: string,
  instanceId: string,
  deviceId: string,
  deviceName: string,
): Promise<string> {
  const pkce = await createPkce()
  const pending: GooglePending = {
    state: pkce.state,
    verifier: pkce.verifier,
    instanceId,
    expiresAt: Date.now() + TTL_MS,
  }
  await writePending(pending)
  return buildGoogleAuthorizationUrl(baseUrl, pending, pkce.challenge, deviceId, deviceName)
}

export async function readGoogleCallback(value: string, instanceId: string): Promise<GoogleCallback> {
  const pending = await readPending()
  if (!pending || pending.instanceId !== instanceId) {
    throw new Error('No matching Google sign-in request was found on this phone.')
  }
  return parseGoogleCallback(value, pending)
}

export async function clearGooglePending(): Promise<void> {
  if (Capacitor.isNativePlatform()) await SecureStorage.removeItem(KEY)
  browserPending = null
}

async function readPending(): Promise<GooglePending | null> {
  const value = Capacitor.isNativePlatform() ? await SecureStorage.getItem(KEY) : browserPending
  if (!value) return null
  try { return JSON.parse(value) as GooglePending } catch { await clearGooglePending(); return null }
}

async function writePending(pending: GooglePending): Promise<void> {
  const value = JSON.stringify(pending)
  if (Capacitor.isNativePlatform()) await SecureStorage.setItem(KEY, value)
  else browserPending = value
}
