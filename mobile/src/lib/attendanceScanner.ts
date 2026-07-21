import { BarcodeFormat, BarcodeScanner } from '@capacitor-mlkit/barcode-scanning'

export async function scanAttendanceQr(): Promise<string> {
  const permission = await BarcodeScanner.requestPermissions()
  if (permission.camera !== 'granted') throw new Error('Camera permission is required to scan the attendance QR code.')
  const result = await BarcodeScanner.scan({ formats: [BarcodeFormat.QrCode] })
  const value = result.barcodes[0]?.rawValue
  if (!value) throw new Error('No QR code was detected. Please try again.')
  return value
}
