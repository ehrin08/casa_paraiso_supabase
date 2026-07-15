import { Capacitor } from '@capacitor/core'
import { SecureStorage } from '@aparajita/capacitor-secure-storage'

const KEY = 'casa.mobile.session'
let browserSession: string | null = null

export interface StoredSession { token: string; expiresAt: string; instanceId: string }

export async function readSession(): Promise<StoredSession | null> {
  const value = Capacitor.isNativePlatform() ? await SecureStorage.getItem(KEY) : browserSession
  if (!value) return null
  try { return JSON.parse(value) as StoredSession } catch { await clearSession(); return null }
}

export async function writeSession(value: StoredSession): Promise<void> {
  const serialized = JSON.stringify(value)
  if (Capacitor.isNativePlatform()) await SecureStorage.setItem(KEY, serialized)
  else browserSession = serialized
}

export async function clearSession(): Promise<void> {
  if (Capacitor.isNativePlatform()) await SecureStorage.removeItem(KEY)
  browserSession = null
}
