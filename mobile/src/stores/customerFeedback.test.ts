import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { customerFeedback, submitCustomerFeedback } from '../lib/api'
import { useCustomerFeedbackStore } from './customerFeedback'

vi.mock('../lib/api', () => ({
  apiError: (reason: unknown) => reason,
  customerFeedback: vi.fn(),
  submitCustomerFeedback: vi.fn(),
}))

const response = {
  data: [{ id: 7, rating: 5, comment: 'Relaxing', sentiment: 'positive', submitted_at: null, service: null, appointment: null }],
  eligible_appointments: [{ id: 9, appointment_number: 'APT-9', completed_at: null, service: null, therapist: null }],
  summary: { awaiting_feedback: 1, submitted: 1 },
  meta: { current_page: 1, last_page: 1, per_page: 15, total: 1, from: 1, to: 1 },
}

describe('customer feedback store', () => {
  beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks() })

  it('loads feedback history and eligible visits', async () => {
    vi.mocked(customerFeedback).mockResolvedValue(response as never)
    const store = useCustomerFeedbackStore()

    await store.load()

    expect(store.feedback[0]?.rating).toBe(5)
    expect(store.eligibleAppointments[0]?.appointment_number).toBe('APT-9')
    expect(store.summary).toEqual({ awaiting_feedback: 1, submitted: 1 })
  })

  it('submits trimmed feedback and refreshes the first page', async () => {
    vi.mocked(customerFeedback).mockResolvedValue(response as never)
    vi.mocked(submitCustomerFeedback).mockResolvedValue({ data: response.data[0], message: 'Thank you.' } as never)
    const store = useCustomerFeedbackStore()

    expect(await store.submit(9, 4, '  Salamat  ')).toBe(true)
    expect(submitCustomerFeedback).toHaveBeenCalledWith({ appointment_id: 9, rating: 4, comment: 'Salamat' })
    expect(customerFeedback).toHaveBeenCalledWith(1)
    expect(store.notice).toBe('Thank you.')
  })
})
