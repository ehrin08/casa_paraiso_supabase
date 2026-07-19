import type { AvailabilitySlot, BookingAvailability } from './api'

export interface BookingCalendarDay {
  key: string
  label: string
  date: string | null
  available: boolean
  previewSlots: AvailabilitySlot[]
  moreSlots: number
}

const previewLimit = 2

export function monthLabel(month: string): string {
  const [year, monthNumber] = month.split('-').map(Number)
  return new Intl.DateTimeFormat('en-PH', { month: 'long', year: 'numeric' })
    .format(new Date(year, monthNumber - 1, 1))
}

export function shiftMonth(month: string, amount: number): string {
  const [year, monthNumber] = month.split('-').map(Number)
  const date = new Date(year, monthNumber - 1 + amount, 1)
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}

export function buildBookingCalendar(month: string, availability: BookingAvailability | null): BookingCalendarDay[] {
  const [year, monthNumber] = month.split('-').map(Number)
  if (!year || !monthNumber) return []

  const firstDay = new Date(year, monthNumber - 1, 1)
  const daysInMonth = new Date(year, monthNumber, 0).getDate()
  const dates = availability?.dates ?? {}
  const days: BookingCalendarDay[] = Array.from({ length: firstDay.getDay() }, (_, index) => ({
    key: `blank-${index}`,
    label: '',
    date: null,
    available: false,
    previewSlots: [],
    moreSlots: 0,
  }))

  for (let day = 1; day <= daysInMonth; day += 1) {
    const date = `${year}-${String(monthNumber).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    const slots = dates[date] ?? []
    days.push({
      key: date,
      label: String(day),
      date,
      available: slots.length > 0,
      previewSlots: slots.slice(0, previewLimit),
      moreSlots: Math.max(0, slots.length - previewLimit),
    })
  }

  return days
}
