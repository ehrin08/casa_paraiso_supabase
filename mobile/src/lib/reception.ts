export function manilaDateTimeInput(value: string | null): string {
  if (!value) return ''
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).formatToParts(new Date(value)).reduce<Record<string, string>>((result, part) => { result[part.type] = part.value; return result }, {})
  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`
}

export function manilaInputToIso(value: string): string {
  return value ? `${value}:00+08:00` : ''
}

export function paymentStatusLabel(status: string): string {
  return ({ unpaid: 'Unpaid', partial: 'Partially paid', paid: 'Paid', refunded: 'Refunded', void: 'Void' } as Record<string, string>)[status] ?? status
}
