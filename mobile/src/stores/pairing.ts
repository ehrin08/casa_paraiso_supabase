import { Preferences } from '@capacitor/preferences'
import { Network } from '@capacitor/network'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { BACKEND_URL, fetchMeta, validateMeta } from '../lib/pairing'

const STORAGE_KEY = 'casa.mobile.pairing'

interface SavedPairing { url: string; instanceId: string; pairedAt: string; deployment: 'production' }

export const usePairingStore = defineStore('pairing', () => {
  const url = ref('')
  const instanceId = ref('')
  const pairedAt = ref('')
  const status = ref<'unpaired' | 'validating' | 'paired' | 'unreachable' | 'mismatch'>('unpaired')
  const error = ref('')
  const attempts = ref(0)
  const online = ref(true)
  const supportedAuth = ref<string[]>([])
  let hydrationTask: Promise<boolean> | null = null
  let bootstrapTask: Promise<boolean> | null = null

  function connectionLost(): void {
    if (status.value === 'validating') return
    status.value = 'unreachable'
    error.value = 'The app could not verify a connection to Casa Paraiso. Check your internet, then choose Check connection.'
  }

  function connectionConfirmed(): void {
    if (status.value !== 'unreachable') return
    status.value = 'paired'
    error.value = ''
  }

  if (typeof window !== 'undefined') {
    window.addEventListener('casa:connection-lost', connectionLost)
    window.addEventListener('casa:connection-confirmed', connectionConfirmed)
  }

  function hydrate(): Promise<boolean> {
    if (hydrationTask) return hydrationTask
    hydrationTask = (async () => {
      online.value = (await Network.getStatus()).connected
      void Network.addListener('networkStatusChange', ({ connected }) => { online.value = connected })
      url.value = BACKEND_URL
      const stored = await Preferences.get({ key: STORAGE_KEY })
      let saved: SavedPairing | null = null
      if (stored.value) {
        try { saved = JSON.parse(stored.value) as SavedPairing } catch { await Preferences.remove({ key: STORAGE_KEY }) }
      }
      if (saved?.url === BACKEND_URL && saved.instanceId && saved.pairedAt) {
        instanceId.value = saved.instanceId
        pairedAt.value = saved.pairedAt
      }
      return bootstrap()
    })()
    return hydrationTask
  }
  function bootstrap(): Promise<boolean> {
    if (bootstrapTask) return bootstrapTask
    bootstrapTask = (async () => {
      url.value ||= BACKEND_URL
      status.value = 'validating'
      error.value = ''
      attempts.value++
      try {
        online.value = (await Network.getStatus()).connected
        if (!online.value) throw new Error('No internet connection is available.')
        const meta = await fetchMeta(url.value)
        validateMeta(meta)
        supportedAuth.value = meta.data.supported_auth
        const saved: SavedPairing = { url: BACKEND_URL, instanceId: meta.data.instance_id, pairedAt: meta.data.server_time, deployment: 'production' }
        await Preferences.set({ key: STORAGE_KEY, value: JSON.stringify(saved) })
        instanceId.value = saved.instanceId
        pairedAt.value = saved.pairedAt
        status.value = 'paired'
        return true
      } catch (reason) {
        const message = reason instanceof Error ? reason.message : 'The server connection could not be verified.'
        status.value = 'unreachable'
        error.value = `${message} The Render server may still be waking after being idle; check again in a moment.`
        return false
      } finally { bootstrapTask = null }
    })()
    return bootstrapTask
  }
  async function ensurePaired(): Promise<boolean> {
    const initialized = await hydrate()
    return initialized || status.value === 'paired' ? true : bootstrap()
  }

  return { url, instanceId, pairedAt, status, error, attempts, online, supportedAuth, hydrate, bootstrap, ensurePaired }
})
