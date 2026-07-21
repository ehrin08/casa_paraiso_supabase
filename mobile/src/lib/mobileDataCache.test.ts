import { beforeEach, describe, expect, it, vi } from 'vitest'
import { hasMobileData, invalidateMobileData, isMobileDataFresh, loadMobileData } from './mobileDataCache'

describe('mobile data cache', () => {
  beforeEach(() => { invalidateMobileData(); vi.restoreAllMocks() })

  it('uses fresh data without repeating the request', async () => {
    const task = vi.fn(async () => undefined)
    await loadMobileData('customer:appointments', 60_000, task)
    await loadMobileData('customer:appointments', 60_000, task)
    expect(task).toHaveBeenCalledTimes(1)
    expect(hasMobileData('customer:appointments')).toBe(true)
    expect(isMobileDataFresh('customer:appointments', 60_000)).toBe(true)
  })

  it('deduplicates concurrent requests', async () => {
    let release = (): void => undefined
    const pending = new Promise<void>(resolve => { release = resolve })
    const task = vi.fn(() => pending)
    const first = loadMobileData('staff:schedule', 60_000, task)
    const second = loadMobileData('staff:schedule', 60_000, task)
    release()
    await Promise.all([first, second])
    expect(task).toHaveBeenCalledTimes(1)
  })

  it('forces refresh and supports scoped invalidation', async () => {
    const task = vi.fn(async () => undefined)
    await loadMobileData('admin:staff', 60_000, task)
    await loadMobileData('admin:staff', 60_000, task, true)
    expect(task).toHaveBeenCalledTimes(2)
    invalidateMobileData('admin:')
    expect(hasMobileData('admin:staff')).toBe(false)
  })

  it('does not mark failed requests as loaded', async () => {
    await expect(loadMobileData('customer:profile', 60_000, async () => { throw new Error('offline') })).rejects.toThrow('offline')
    expect(hasMobileData('customer:profile')).toBe(false)
  })
})
