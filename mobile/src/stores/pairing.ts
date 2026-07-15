import { Preferences } from '@capacitor/preferences'
import { Network } from '@capacitor/network'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchMeta, normalizeBackendUrl, parsePairingDeepLink, validateMeta, verifyPairing } from '../lib/pairing'

const STORAGE_KEY = 'casa.mobile.pairing'

interface SavedPairing { url: string; instanceId: string; pairedAt: string }

export const usePairingStore = defineStore('pairing', () => {
  const url = ref('')
  const code = ref('')
  const instanceId = ref('')
  const pairedAt = ref('')
  const status = ref<'unpaired' | 'validating' | 'verifying' | 'paired' | 'unreachable' | 'mismatch'>('unpaired')
  const error = ref('')
  const online = ref(true)

  async function hydrate(): Promise<void> {
    online.value = (await Network.getStatus()).connected
    void Network.addListener('networkStatusChange', ({ connected }) => { online.value = connected })
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
      status.value = 'paired'
    } catch (reason) {
      const message = reason instanceof Error ? reason.message : 'The saved server cannot be reached.'
      status.value = message.includes('different Casa Paraiso') ? 'mismatch' : 'unreachable'
      error.value = message
    }
  }

  async function pair(): Promise<void> {
    status.value = 'validating'
    error.value = ''
    try {
      const normalized = normalizeBackendUrl(url.value)
      const meta = await fetchMeta(normalized)
      if (!meta.data.pairing.enabled) throw new Error('Pairing is not enabled on this tunnel. Start the demo helper again.')
      if (!/^\d{8}$/.test(code.value)) throw new Error('Enter the eight-digit pairing code.')
      status.value = 'verifying'
      const receipt = await verifyPairing(normalized, meta.data.instance_id, code.value)
      const saved: SavedPairing = { url: normalized, instanceId: receipt.data.instance_id, pairedAt: receipt.data.paired_at }
      await Preferences.set({ key: STORAGE_KEY, value: JSON.stringify(saved) })
      url.value = saved.url
      instanceId.value = saved.instanceId
      pairedAt.value = saved.pairedAt
      code.value = ''
      status.value = 'paired'
    } catch (reason) {
      status.value = 'unpaired'
      error.value = reason instanceof Error ? reason.message : 'Pairing could not be completed.'
    }
  }

  function acceptDeepLink(value: string): void {
    const parsed = parsePairingDeepLink(value)
    if (!parsed) return
    url.value = parsed.url
    code.value = parsed.code
    status.value = 'unpaired'
    error.value = ''
  }

  return { url, code, instanceId, pairedAt, status, error, online, hydrate, revalidate, pair, acceptDeepLink }
})
