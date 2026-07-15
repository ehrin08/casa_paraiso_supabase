import { describe, expect, it } from 'vitest'
import { commissionRate, commissionStatusLabel, sentimentLabel } from './staff'

describe('therapist workspace formatting', () => {
  it('labels payout and feedback states for operational cards', () => {
    expect(commissionStatusLabel('pending')).toBe('Pending payout')
    expect(commissionStatusLabel('paid')).toBe('Paid out')
    expect(sentimentLabel('positive')).toBe('Positive')
  })

  it('formats snapshot commission rates as percentages', () => {
    expect(commissionRate('0.2200')).toBe('22%')
  })
})
