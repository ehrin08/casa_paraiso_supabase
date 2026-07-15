export function appointmentStatusLabel(status: string): string {
  return ({ confirmed: 'Confirmed', completed: 'Completed', cancelled: 'Cancelled', no_show: 'No-show' } as Record<string, string>)[status] ?? status
}

export function formatPeso(value: string): string {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value))
}

export function formatAppointmentDate(value: string | null): string {
  if (!value) return 'Schedule unavailable'
  return new Intl.DateTimeFormat('en-PH', {
    timeZone: 'Asia/Manila',
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(value))
}
