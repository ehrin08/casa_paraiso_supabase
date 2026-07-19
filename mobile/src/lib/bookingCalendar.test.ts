import { describe, expect, it } from 'vitest'
import { buildBookingCalendar, monthLabel, shiftMonth } from './bookingCalendar'

const slot = (time: string) => ({ time, starts_at: `2026-07-15T${time}:00+08:00`, ends_at: `2026-07-15T${time}:30:00+08:00`, label: time, staff_count: 1 })

describe('booking calendar helpers', () => {
  it('builds leading blanks and two slot previews for available dates', () => {
    const days = buildBookingCalendar('2026-07', { month: '2026-07', service_id: 1, preferred_staff_profile_id: null, dates: { '2026-07-15': [slot('13:00'), slot('13:30'), slot('14:00')] } })
    const day = days.find(item => item.date === '2026-07-15')

    expect(days.slice(0, 3)).toHaveLength(3)
    expect(day).toMatchObject({ available: true, previewSlots: [slot('13:00'), slot('13:30')], moreSlots: 1 })
    expect(days.find(item => item.date === '2026-07-14')).toMatchObject({ available: false, moreSlots: 0 })
  })

  it('formats and shifts month navigation across year boundaries', () => {
    expect(monthLabel('2026-07')).toContain('July 2026')
    expect(shiftMonth('2026-01', -1)).toBe('2025-12')
    expect(shiftMonth('2026-12', 1)).toBe('2027-01')
  })
})
