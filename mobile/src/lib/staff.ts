export function commissionStatusLabel(status: string): string {
  return status === 'paid' ? 'Paid out' : 'Pending payout'
}

export function sentimentLabel(sentiment: string): string {
  return sentiment.charAt(0).toUpperCase() + sentiment.slice(1)
}

export function commissionRate(value: string): string {
  return `${Math.round(Number(value) * 100)}%`
}
