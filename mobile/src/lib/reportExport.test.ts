import { describe, expect, it } from 'vitest'
import { reportFilename } from './reportExport'

describe('report export', () => {
  it('creates a portable timestamped CSV filename', () => {
    expect(reportFilename('transactions', new Date('2026-07-15T08:30:45.000Z'))).toBe('casa-paraiso-transactions-2026-07-15T08-30-45.csv')
  })
})
