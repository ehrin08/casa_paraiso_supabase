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

  async function hydrate(): Promise<void> {
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
    await bootstrap()
  }

  async function bootstrap(): Promise<boolean> {
    status.value = 'validating'
    error.value = ''
    attempts.value++
    try {
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
      const message = reason instanceof Error ? reason.message : 'The saved server cannot be reached.'
      status.value = 'unreachable'
      error.value = `${message} Render may still be waking after being idle.`
      return false
    }
  }

  return { url, instanceId, pairedAt, status, error, attempts, online, supportedAuth, hydrate, bootstrap }
})
