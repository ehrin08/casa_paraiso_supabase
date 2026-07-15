import { App as CapacitorApp } from '@capacitor/app'
import type { PluginListenerHandle } from '@capacitor/core'
import { nextTick, onBeforeUnmount, type Ref, watch } from 'vue'

export function useModalLifecycle(open: Ref<boolean>, panel: Ref<HTMLElement | null>, close: () => void): void {
  let previous: HTMLElement | null = null
  let backHandle: PluginListenerHandle | null = null

  function keydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') { event.preventDefault(); close(); return }
    if (event.key !== 'Tab' || !panel.value) return
    const controls = [...panel.value.querySelectorAll<HTMLElement>('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
    if (!controls.length) return
    const first = controls[0]; const last = controls[controls.length - 1]
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
  }

  async function activate(): Promise<void> {
    previous = document.activeElement instanceof HTMLElement ? document.activeElement : null
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', keydown)
    backHandle = await CapacitorApp.addListener('backButton', close)
    await nextTick()
    panel.value?.querySelector<HTMLElement>('[data-autofocus], button, input, select, textarea')?.focus()
  }

  async function deactivate(): Promise<void> {
    document.body.style.removeProperty('overflow')
    document.removeEventListener('keydown', keydown)
    await backHandle?.remove(); backHandle = null
    previous?.focus(); previous = null
  }

  watch(open, value => { if (value) void activate(); else void deactivate() }, { immediate: true })
  onBeforeUnmount(() => { void deactivate() })
}
