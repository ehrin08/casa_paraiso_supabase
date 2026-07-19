import { App as CapacitorApp } from '@capacitor/app'
import { Capacitor } from '@capacitor/core'
import { StatusBar } from '@capacitor/status-bar'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { useAuthStore } from './stores/auth'
import { router } from './router'
import { mobileModalDirective } from './components/mobileModalDirective'
import { installLegacyModalObserver } from './components/legacyModalObserver'
import '@fontsource/manrope/latin-400.css'
import '@fontsource/manrope/latin-600.css'
import '@fontsource/manrope/latin-700.css'
import '@fontsource/manrope/latin-800.css'
import '@fontsource/cormorant-garamond/latin-600.css'
import '@fontsource/cormorant-garamond/latin-700.css'
import './style.css'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(router)
app.directive('mobile-modal', mobileModalDirective)
app.mount('#app')
installLegacyModalObserver()

async function applyNativeSafeArea(): Promise<void> {
  if (!Capacitor.isNativePlatform()) return

  try {
    const { height } = await StatusBar.getInfo()
    document.documentElement.style.setProperty('--safe-top', `${height}px`)
  } catch {
    // Keep the CSS environment-variable fallback when the native plugin is unavailable.
  }
}

void applyNativeSafeArea()

const auth = useAuthStore(pinia)

async function handleAppUrl(url: string): Promise<void> {
  if (await auth.completeGoogleSignIn(url)) {
    if (auth.user) await router.replace(`/workspace/${auth.user.workspace}`)
    else await router.replace('/sign-in')
  }
}

const ready = auth.hydrate().then(async () => {
  const launch = await CapacitorApp.getLaunchUrl()
  if (launch?.url) await handleAppUrl(launch.url)
  if (auth.user) await router.replace(`/workspace/${auth.user.workspace}`)
  else await router.replace('/')
})

void CapacitorApp.addListener('appUrlOpen', async ({ url }) => {
  await ready
  await handleAppUrl(url)
  if (auth.user) await router.replace(`/workspace/${auth.user.workspace}`)
  else await router.replace('/')
})
