import { beforeEach, describe, expect, it, vi } from 'vitest'

const { get } = vi.hoisted(() => ({ get: vi.fn() }))

vi.mock('axios', () => ({ default: { get } }))

import { defaultPublicBusinessProfile, loadPublicBusinessProfile } from './publicBusinessProfile'

describe('public business profile', () => {
  beforeEach(() => get.mockReset())

  it('uses the server-approved public contact details when available', async () => {
    get.mockResolvedValueOnce({ data: { data: { business_address: 'Updated address', messenger_url: 'https://m.me/updated' } } })

    await expect(loadPublicBusinessProfile()).resolves.toMatchObject({
      business_address: 'Updated address',
      messenger_url: 'https://m.me/updated',
      facebook_url: defaultPublicBusinessProfile.facebook_url,
    })
  })

  it('retains approved fallback content when the public profile is unavailable', async () => {
    get.mockRejectedValueOnce(new Error('offline'))

    await expect(loadPublicBusinessProfile()).resolves.toEqual(defaultPublicBusinessProfile)
  })
})
