import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  apiError,
  customerProfile,
  updateCustomerProfile,
  updatePassword,
  type CustomerProfileData,
} from '../lib/api'
import { hasMobileData, invalidateMobileData, loadMobileData, REFERENCE_TTL_MS } from '../lib/mobileDataCache'

export const useCustomerProfileStore = defineStore('customerProfile', () => {
  const profile = ref<CustomerProfileData | null>(null)
  const loading = ref(false)
  const refreshing = ref(false)
  const saving = ref(false)
  const changingPassword = ref(false)
  const error = ref('')
  const notice = ref('')
  const fields = ref<Record<string, string[]>>({})

  const cacheKey = 'customer:profile'
  const hasLoaded = () => hasMobileData(cacheKey)
  async function load(force = false): Promise<void> {
    const initial = !hasLoaded()
    if (initial) loading.value = true; else refreshing.value = true
    clearMessages()
    try { await loadMobileData(cacheKey, REFERENCE_TTL_MS, async () => { profile.value = await customerProfile() }, force) }
    catch (reason) { capture(reason) }
    finally { loading.value = false; refreshing.value = false }
  }

  async function save(payload: { name: string; phone: string; address: string; contact_preference: string }): Promise<boolean> {
    saving.value = true
    clearMessages()
    try {
      const response = await updateCustomerProfile({
        name: payload.name,
        phone: payload.phone.trim() || undefined,
        address: payload.address.trim() || undefined,
        contact_preference: payload.contact_preference || undefined,
      })
      profile.value = response.data
      invalidateMobileData('customer:profile')
      notice.value = response.message
      return true
    } catch (reason) { capture(reason); return false }
    finally { saving.value = false }
  }

  async function changePassword(currentPassword: string, password: string, confirmation: string): Promise<string | null> {
    changingPassword.value = true
    clearMessages()
    try {
      return (await updatePassword({
        current_password: currentPassword,
        password,
        password_confirmation: confirmation,
      })).message
    } catch (reason) { capture(reason); return null }
    finally { changingPassword.value = false }
  }

  function clearMessages(): void { error.value = ''; notice.value = ''; fields.value = {} }
  function capture(reason: unknown): void {
    const failure = apiError(reason)
    error.value = failure.message
    fields.value = failure.fields ?? {}
  }

  function reset(): void { profile.value = null; error.value = ''; notice.value = ''; fields.value = {} }
  return { profile, loading, refreshing, saving, changingPassword, error, notice, fields, hasLoaded, load, save, changePassword, reset }
})
