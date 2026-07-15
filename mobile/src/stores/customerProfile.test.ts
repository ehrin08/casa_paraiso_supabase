import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { customerProfile, updateCustomerProfile, updatePassword } from '../lib/api'
import { useCustomerProfileStore } from './customerProfile'

vi.mock('../lib/api', () => ({
  apiError: (reason: unknown) => reason,
  customerProfile: vi.fn(),
  updateCustomerProfile: vi.fn(),
  updatePassword: vi.fn(),
}))

const profile = {
  name: 'Demo Customer', email: 'customer@example.test', phone: null, address: null,
  contact_preference: null, customer_code: 'CP-1', has_password: true, google_linked: false,
  contact_preferences: [{ value: 'email', label: 'Email' }],
}

describe('customer profile store', () => {
  beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks() })

  it('loads and saves normalized profile fields', async () => {
    vi.mocked(customerProfile).mockResolvedValue(profile)
    vi.mocked(updateCustomerProfile).mockResolvedValue({ data: { ...profile, phone: '0917' }, message: 'Profile updated.' })
    const store = useCustomerProfileStore()

    await store.load()
    expect(store.profile?.customer_code).toBe('CP-1')
    expect(await store.save({ name: 'Demo Customer', phone: ' 0917 ', address: ' ', contact_preference: '' })).toBe(true)
    expect(updateCustomerProfile).toHaveBeenCalledWith({ name: 'Demo Customer', phone: '0917', address: undefined, contact_preference: undefined })
    expect(store.notice).toBe('Profile updated.')
  })

  it('returns the reauthentication message after password change', async () => {
    vi.mocked(updatePassword).mockResolvedValue({ message: 'Sign in again.' })
    const store = useCustomerProfileStore()

    await expect(store.changePassword('old', 'new-password', 'new-password')).resolves.toBe('Sign in again.')
    expect(updatePassword).toHaveBeenCalledWith({ current_password: 'old', password: 'new-password', password_confirmation: 'new-password' })
  })
})
