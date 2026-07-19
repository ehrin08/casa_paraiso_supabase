import { Preferences } from '@capacitor/preferences'
import { Network } from '@capacitor/network'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchMeta, isProductionBuild, normalizeBackendUrl, parsePairingDeepLink, PRODUCTION_BACKEND_URL, validateMeta } from '../lib/pairing'

const STORAGE_KEY = 'casa.mobile.pairing'

interface SavedPairing { url: string; instanceId: string; pairedAt: string; deployment?: 'production' | 'demo' }

export const usePairingStore = defineStore('pairing', () => {
  const url = ref('')
  const instanceId = ref('')
  const pairedAt = ref('')
  const status = ref<'unpaired' | 'validating' | 'paired' | 'unreachable' | 'mismatch'>('unpaired')
  const error = ref('')
  const online = ref(true)
  const supportedAuth = ref<string[]>([])

  async function hydrate(): Promise<void> {
    online.value = (await Network.getStatus()).connected
    void Network.addListener('networkStatusChange', ({ connected }) => { online.value = connected })
    if (isProductionBuild()) {
      url.value = PRODUCTION_BACKEND_URL
      await pair()
      return
    }
    const stored = await Preferences.get({ key: STORAGE_KEY })
    if (!stored.value) return

    let saved: SavedPairing
    try {
      saved = JSON.parse(stored.value) as SavedPairing
      if (!saved.url || !saved.instanceId || !saved.pairedAt) throw new Error('Incomplete pairing state')
    } catch {
      await Preferences.remove({ key: STORAGE_KEY })
      return
    }
    url.value = saved.url
    instanceId.value = saved.instanceId
    pairedAt.value = saved.pairedAt
    await revalidate()
  }

  async function revalidate(): Promise<void> {
    if (!url.value || !instanceId.value) return
    status.value = 'validating'
    error.value = ''
    try {
      const meta = await fetchMeta(url.value)
      validateMeta(meta, instanceId.value)
      supportedAuth.value = meta.data.supported_auth
      status.value = 'paired'
    } catch (reason) {
      const message = reason instanceof Error ? reason.message : 'The saved server cannot be reached.'
      status.value = message.includes('different Casa Paraiso') ? 'mismatch' : 'unreachable'
      error.value = message
    }
  }

  async function pair(): Promise<boolean> {
    status.value = 'validating'
    error.value = ''
    try {
      const normalized = normalizeBackendUrl(url.value)
      const meta = await fetchMeta(normalized)
      supportedAuth.value = meta.data.supported_auth
      if (!meta.data.pairing.enabled) throw new Error('Pairing is not enabled on this server.')
      const saved: SavedPairing = {
        url: normalized,
        instanceId: meta.data.instance_id,
        pairedAt: meta.data.server_time,
        deployment: isProductionBuild() ? 'production' : 'demo',
      }
      await Preferences.set({ key: STORAGE_KEY, value: JSON.stringify(saved) })
      url.value = saved.url
      instanceId.value = saved.instanceId
      pairedAt.value = saved.pairedAt
      status.value = 'paired'
      return true
    } catch (reason) {
      status.value = 'unpaired'
      error.value = reason instanceof Error ? reason.message : 'Pairing could not be completed.'
      return false
    }
  }

  async function acceptDeepLink(value: string): Promise<boolean> {
    const parsed = parsePairingDeepLink(value)
    if (!parsed) return false
    url.value = parsed.url
    status.value = 'unpaired'
    error.value = ''
    return pair()
  }

  return { url, instanceId, pairedAt, status, error, online, supportedAuth, hydrate, revalidate, pair, acceptDeepLink }
})
