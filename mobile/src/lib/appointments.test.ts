import { describe, expect, it } from 'vitest'
import { appointmentStatusLabel, bookingExpectedAmount, formatAppointmentDate, formatBookingDay, formatPeso } from './appointments'

describe('appointment display helpers', () => {
  it('uses customer-friendly status labels', () => {
    expect(appointmentStatusLabel('no_show')).toBe('No-show')
    expect(appointmentStatusLabel('confirmed')).toBe('Confirmed')
  })

  it('formats money and server timestamps for the Philippines', () => {
    expect(formatPeso('499.00')).toContain('499.00')
    expect(formatAppointmentDate('2026-07-15T14:00:00+08:00')).toContain('Jul 15, 2026')
  })

  it('formats booking days and keeps rewards outside the paid total', () => {
    expect(formatBookingDay('2026-07-15')).toContain('Jul 15')
    expect(bookingExpectedAmount('499.00', ['200.00', '299.00'])).toBe('998.00')
  })
})
