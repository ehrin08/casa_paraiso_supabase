import { describe, expect, it } from 'vitest'
import { manilaDateTimeInput, manilaInputToIso, paymentStatusLabel } from './reception'

describe('reception mobile helpers', () => {
  it('keeps operational appointment inputs in Asia/Manila', () => {
    expect(manilaDateTimeInput('2026-07-15T14:30:00+08:00')).toBe('2026-07-15T14:30')
    expect(manilaInputToIso('2026-07-15T14:30')).toBe('2026-07-15T14:30:00+08:00')
  })

  it('uses front-desk friendly payment labels', () => {
    expect(paymentStatusLabel('partial')).toBe('Partially paid')
    expect(paymentStatusLabel('paid')).toBe('Paid')
  })
})
