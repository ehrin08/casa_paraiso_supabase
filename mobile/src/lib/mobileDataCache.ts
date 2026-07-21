export const OPERATIONAL_TTL_MS = 60_000
export const REFERENCE_TTL_MS = 300_000

type CacheEntry = {
  updatedAt?: number
  inFlight?: Promise<void>
}

const entries = new Map<string, CacheEntry>()

export function hasMobileData(key: string): boolean {
  return entries.get(key)?.updatedAt !== undefined
}

export function isMobileDataFresh(key: string, ttlMs: number, now = Date.now()): boolean {
  const updatedAt = entries.get(key)?.updatedAt
  return updatedAt !== undefined && now - updatedAt < ttlMs
}

export async function loadMobileData(
  key: string,
  ttlMs: number,
  task: () => Promise<void>,
  force = false,
): Promise<'cached' | 'loaded'> {
  const current = entries.get(key)
  if (!force && isMobileDataFresh(key, ttlMs)) return 'cached'
  if (current?.inFlight) {
    await current.inFlight
    return 'loaded'
  }

  const entry = current ?? {}
  const inFlight = task().then(() => {
    entry.updatedAt = Date.now()
  }).finally(() => {
    delete entry.inFlight
  })
  entry.inFlight = inFlight
  entries.set(key, entry)
  await inFlight
  return 'loaded'
}

export function invalidateMobileData(prefix?: string): void {
  if (!prefix) {
    entries.clear()
    return
  }

  for (const key of entries.keys()) {
    if (key.startsWith(prefix)) entries.delete(key)
  }
}

export function scheduleMobilePreload(task: () => Promise<unknown>, delayMs = 350): () => void {
  const timer = window.setTimeout(() => { void task() }, delayMs)
  return () => window.clearTimeout(timer)
}
