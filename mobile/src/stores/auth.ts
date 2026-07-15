import { Preferences } from '@capacitor/preferences'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { apiError, configureApi, login, logout, me, setToken, type MobileUser } from '../lib/api'
import { clearSession, readSession, writeSession } from '../lib/session'
import { usePairingStore } from './pairing'

const DEVICE_KEY = 'casa.mobile.device-id'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<MobileUser | null>(null)
  const booting = ref(true)
  const working = ref(false)
  const error = ref('')
  const authenticated = computed(() => user.value !== null)

  async function deviceId(): Promise<string> {
    const saved = await Preferences.get({ key: DEVICE_KEY })
    if (saved.value) return saved.value
    const id = crypto.randomUUID()
    await Preferences.set({ key: DEVICE_KEY, value: id })
    return id
  }

  async function hydrate(): Promise<void> {
    booting.value = true
    const pairing = usePairingStore()
    await pairing.hydrate()
    if (pairing.status !== 'paired') { booting.value = false; return }
    configureApi(pairing.url)
    const saved = await readSession()
    if (!saved || saved.instanceId !== pairing.instanceId || Date.parse(saved.expiresAt) <= Date.now()) { await clear(); booting.value = false; return }
    setToken(saved.token)
    try { user.value = await me() } catch (reason) {
      const failure = apiError(reason)
      if (failure.code === 'NETWORK_ERROR') error.value = failure.message
      else { await clear(); error.value = failure.message }
    } finally { booting.value = false }
  }

  async function signIn(email: string, password: string): Promise<boolean> {
    const pairing = usePairingStore()
    if (pairing.status !== 'paired') { error.value = 'Pair this phone before signing in.'; return false }
    working.value = true; error.value = ''
    try {
      configureApi(pairing.url)
      const response = await login({ email, password, device_id: await deviceId(), device_name: 'Casa Paraiso Android' })
      await writeSession({ token: response.token, expiresAt: response.expires_at, instanceId: pairing.instanceId })
      setToken(response.token); user.value = response.user
      return true
    } catch (reason) { error.value = apiError(reason).message; return false } finally { working.value = false }
  }

  async function signOut(): Promise<boolean> {
    working.value = true; error.value = ''
    try { await logout(); await clear(); return true }
    catch (reason) { error.value = apiError(reason).message; return false }
    finally { working.value = false }
  }

  async function clear(): Promise<void> { user.value = null; setToken(''); await clearSession() }
  function applyProfile(name: string, phone: string | null): void {
    if (user.value) user.value = { ...user.value, name, phone }
  }
  return { user, booting, working, error, authenticated, hydrate, signIn, signOut, clear, applyProfile }
})
