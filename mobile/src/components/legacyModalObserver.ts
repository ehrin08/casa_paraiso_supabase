import { App as CapacitorApp } from '@capacitor/app'
import type { PluginListenerHandle } from '@capacitor/core'

export function installLegacyModalObserver(): () => void {
  let active: HTMLElement | null = null
  let previous: HTMLElement | null = null
  let backHandle: PluginListenerHandle | null = null
  let generation = 0

  const close = (): void => { active?.querySelector<HTMLButtonElement>('header button[aria-label*="Close"]')?.click() }
  const controls = (): HTMLElement[] => active ? [...active.querySelectorAll<HTMLElement>('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')] : []
  const keydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') { event.preventDefault(); close(); return }
    if (event.key !== 'Tab') return
    const available = controls(); if (!available.length) return
    const first = available[0]; const last = available[available.length - 1]
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
  }
  const deactivate = (): void => {
    if (!active) return
    generation += 1
    active.removeAttribute('data-casa-modal-managed')
    document.removeEventListener('keydown', keydown)
    document.body.style.removeProperty('overflow')
    void backHandle?.remove(); backHandle = null; active = null
    previous?.focus(); previous = null
  }
  const activate = (element: HTMLElement): void => {
    deactivate(); active = element; previous = document.activeElement instanceof HTMLElement ? document.activeElement : null
    const currentGeneration = ++generation
    element.dataset.casaModalManaged = 'observer'; document.body.style.overflow = 'hidden'; document.addEventListener('keydown', keydown)
    void CapacitorApp.addListener('backButton', close).then(handle => { if (currentGeneration !== generation) void handle.remove(); else backHandle = handle })
    requestAnimationFrame(() => element.querySelector<HTMLElement>('header button, input, select, textarea')?.focus())
  }
  const scan = (): void => {
    if (active && !active.isConnected) deactivate()
    const candidates = [...document.querySelectorAll<HTMLElement>('.sheet:not([data-casa-modal-managed]), .feedback-sheet:not([data-casa-modal-managed])')]
    if (candidates.length) activate(candidates[candidates.length - 1])
  }
  const observer = new MutationObserver(scan)
  observer.observe(document.body, { childList: true, subtree: true })
  scan()
  return () => { observer.disconnect(); deactivate() }
}
