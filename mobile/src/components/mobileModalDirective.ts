import { App as CapacitorApp } from '@capacitor/app'
import type { PluginListenerHandle } from '@capacitor/core'
import type { Directive } from 'vue'

interface ModalElement extends HTMLElement {
  __casaModalCleanup?: () => void
}

export const mobileModalDirective: Directive<ModalElement, () => void> = {
  mounted(element, binding) {
    element.dataset.casaModalManaged = 'directive'
    const previous = document.activeElement instanceof HTMLElement ? document.activeElement : null
    let backHandle: PluginListenerHandle | null = null
    let disposed = false
    const controls = (): HTMLElement[] => [...element.querySelectorAll<HTMLElement>('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
    const close = (): void => binding.value()
    const keydown = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') { event.preventDefault(); close(); return }
      if (event.key !== 'Tab') return
      const available = controls()
      if (!available.length) return
      const first = available[0]; const last = available[available.length - 1]
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
    }
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', keydown)
    void CapacitorApp.addListener('backButton', close).then(handle => { if (disposed) void handle.remove(); else backHandle = handle })
    requestAnimationFrame(() => element.querySelector<HTMLElement>('button, input, select, textarea')?.focus())
    element.__casaModalCleanup = () => {
      disposed = true
      document.body.style.removeProperty('overflow')
      document.removeEventListener('keydown', keydown)
      void backHandle?.remove()
      previous?.focus()
      element.removeAttribute('data-casa-modal-managed')
    }
  },
  beforeUnmount(element) { element.__casaModalCleanup?.() },
}
