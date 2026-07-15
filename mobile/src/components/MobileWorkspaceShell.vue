<script setup lang="ts">
import type { Component } from 'vue'
import { PhSignOut } from '@phosphor-icons/vue'

export interface MobileNavigationItem {
  id: string
  label: string
  icon: Component
}

defineProps<{
  accountLabel: string
  navigationLabel: string
  activeId: string
  items: MobileNavigationItem[]
  working?: boolean
  error?: string | null
}>()

defineEmits<{ select: [id: string]; signOut: [] }>()
</script>

<template>
  <main class="mobile-workspace">
    <header class="mobile-app-bar">
      <div class="mobile-app-bar__brand">
        <span aria-hidden="true">CP</span>
        <strong>Casa Paraiso</strong>
      </div>
      <div class="mobile-app-bar__account">
        <span>{{ accountLabel }}</span>
        <button type="button" aria-label="Sign out" :disabled="working" @click="$emit('signOut')">
          <PhSignOut :size="21" weight="bold" aria-hidden="true" />
        </button>
      </div>
    </header>

    <p v-if="error" class="mobile-workspace__alert alert" role="alert">{{ error }}</p>
    <div class="mobile-workspace__content"><slot /></div>

    <nav class="mobile-dock" :class="`mobile-dock--${items.length}`" :aria-label="navigationLabel">
      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        :class="{ active: activeId === item.id }"
        :aria-current="activeId === item.id ? 'page' : undefined"
        :aria-label="item.label"
        @click="$emit('select', item.id)"
      >
        <component :is="item.icon" :size="22" :weight="activeId === item.id ? 'fill' : 'regular'" aria-hidden="true" />
        <small>{{ item.label }}</small>
      </button>
    </nav>
  </main>
</template>

<style scoped>
.mobile-workspace { min-height: 100dvh; }
.mobile-app-bar { position: sticky; z-index: 18; top: 0; min-height: calc(var(--app-bar-height) + var(--safe-top)); display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: var(--safe-top) .75rem 0; border-bottom: 1px solid var(--casa-border); background: rgb(255 252 247 / 96%); box-shadow: 0 4px 18px rgb(35 38 32 / 6%); backdrop-filter: blur(14px); }
.mobile-app-bar__brand { min-width: 0; display: flex; align-items: center; gap: .55rem; color: var(--casa-deep-palm); }
.mobile-app-bar__brand > span { width: 2rem; height: 2rem; display: grid; place-items: center; flex: 0 0 auto; border-radius: 999px; color: #fff; background: var(--casa-palm); font-family: var(--font-display); font-weight: 700; }
.mobile-app-bar__brand strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: var(--font-display); font-size: 1.15rem; }
.mobile-app-bar__account { min-width: 0; display: flex; align-items: center; justify-content: flex-end; gap: .35rem; }
.mobile-app-bar__account > span { max-width: min(32vw, 10rem); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--casa-muted); font-size: .75rem; font-weight: 700; }
.mobile-app-bar button { width: 48px; min-width: 48px; height: 48px; display: grid; place-items: center; border: 0; border-radius: 999px; color: var(--casa-cacao); background: var(--casa-sand); }
.mobile-workspace__alert { position: fixed; z-index: 24; top: calc(var(--safe-top) + var(--app-bar-height) + .5rem); right: .75rem; left: .75rem; max-width: 40rem; margin: auto; box-shadow: var(--shadow-float); }
.mobile-workspace__content :deep(.page), .mobile-workspace__content :deep(.customer-workspace), .mobile-workspace__content :deep(.feedback-page), .mobile-workspace__content :deep(.profile-page) { padding-top: 1.25rem !important; padding-bottom: calc(var(--dock-height) + var(--safe-bottom) + 1.5rem) !important; }
.mobile-dock { position: fixed; z-index: 20; right: 0; bottom: 0; left: 0; display: grid; padding: .35rem .4rem max(.35rem, var(--safe-bottom)); border-top: 1px solid var(--casa-border); background: rgb(255 252 247 / 97%); box-shadow: 0 -8px 24px rgb(35 38 32 / 9%); backdrop-filter: blur(14px); }
.mobile-dock--3 { grid-template-columns: repeat(3, 1fr); }
.mobile-dock--4 { grid-template-columns: repeat(4, 1fr); }
.mobile-dock--5 { grid-template-columns: repeat(5, 1fr); }
.mobile-dock button { min-width: 0; min-height: 56px; display: grid; place-items: center; align-content: center; gap: .15rem; border: 0; border-radius: .8rem; color: var(--casa-muted); background: transparent; }
.mobile-dock button.active { color: var(--casa-deep-palm); background: var(--casa-success-bg); }
.mobile-dock small { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: clamp(.61rem, 2.7vw, .72rem); font-weight: 800; }
@media (max-width: 340px) { .mobile-dock--5 small { font-size: .56rem; } .mobile-app-bar__brand strong { display: none; } }
@media (min-width: 768px) { .mobile-app-bar { padding-inline: 1.5rem; } .mobile-dock { left: 50%; width: min(100%, 48rem); border-right: 1px solid var(--casa-border); border-left: 1px solid var(--casa-border); border-radius: 1rem 1rem 0 0; transform: translateX(-50%); } }
</style>
