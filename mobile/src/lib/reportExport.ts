import { Capacitor } from '@capacitor/core'
import { Directory, Encoding, Filesystem } from '@capacitor/filesystem'
import { Share } from '@capacitor/share'
import { exportAdminReport } from './api'

export function reportFilename(type: string, now = new Date()): string {
  return `casa-paraiso-${type}-${now.toISOString().replace(/[:.]/g, '-').slice(0, 19)}.csv`
}

export async function shareAdminReport(type: string, params: Record<string, string | undefined>): Promise<string> {
  const blob = await exportAdminReport({ ...params, type })
  const filename = reportFilename(type)
  if (Capacitor.isNativePlatform()) {
    const saved = await Filesystem.writeFile({ path: filename, data: await blob.text(), directory: Directory.Cache, encoding: Encoding.UTF8, recursive: true })
    await Share.share({ title: `Casa Paraiso ${type} report`, text: 'Casa Paraiso management report', url: saved.uri, dialogTitle: 'Share report' })
  } else {
    const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = filename; link.click(); URL.revokeObjectURL(url)
  }
  return filename
}
