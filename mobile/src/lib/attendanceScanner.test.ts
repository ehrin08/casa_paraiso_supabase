import { beforeEach, describe, expect, it, vi } from 'vitest'

const { checkPermissions, requestPermissions, scan } = vi.hoisted(() => ({
  checkPermissions: vi.fn(),
  requestPermissions: vi.fn(),
  scan: vi.fn(),
}))

vi.mock('@capacitor-mlkit/barcode-scanning', () => ({
  BarcodeFormat: { QrCode: 'qr_code' },
  BarcodeScanner: { checkPermissions, requestPermissions, scan },
}))

import { scanAttendanceQr } from './attendanceScanner'

describe('attendance scanner', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('prompts for camera access only when the scanner is opened without permission', async () => {
    checkPermissions.mockResolvedValue({ camera: 'prompt' })
    requestPermissions.mockResolvedValue({ camera: 'granted' })
    scan.mockResolvedValue({ barcodes: [{ rawValue: 'signed-qr-payload' }] })

    await expect(scanAttendanceQr()).resolves.toBe('signed-qr-payload')

    expect(requestPermissions).toHaveBeenCalledOnce()
    expect(scan).toHaveBeenCalledWith({ formats: ['qr_code'] })
  })

  it('does not open the scanner when camera access is denied', async () => {
    checkPermissions.mockResolvedValue({ camera: 'denied' })
    requestPermissions.mockResolvedValue({ camera: 'denied' })

    await expect(scanAttendanceQr()).rejects.toThrow('Camera permission is required')
    expect(scan).not.toHaveBeenCalled()
  })
})
