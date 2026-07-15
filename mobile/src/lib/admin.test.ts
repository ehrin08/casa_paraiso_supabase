import { describe, expect, it } from 'vitest'
import { dayNames, serviceStateLabel, therapistStateLabel } from './admin'

describe('admin display helpers', () => {
  it('labels service and therapist availability clearly', () => {
    expect(serviceStateLabel(false)).toBe('Inactive')
    expect(therapistStateLabel(true, false)).toBe('Not bookable')
    expect(dayNames[1]).toBe('Monday')
  })
})
