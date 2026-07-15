import { App as CapacitorApp } from '@capacitor/app'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { usePairingStore } from './stores/pairing'
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

const pairing = usePairingStore(pinia)
const auth = useAuthStore(pinia)

async function handleAppUrl(url: string): Promise<void> {
  if (await auth.completeGoogleSignIn(url)) {
    if (auth.user) await router.replace(`/workspace/${auth.user.workspace}`)
    else await router.replace('/sign-in')
    return
  }
  await pairing.acceptDeepLink(url)
}

const ready = auth.hydrate().then(async () => {
  const launch = await CapacitorApp.getLaunchUrl()
  if (launch?.url) await handleAppUrl(launch.url)
  if (auth.user) await router.replace(`/workspace/${auth.user.workspace}`)
  else if (pairing.status === 'paired') await router.replace('/sign-in')
})

void CapacitorApp.addListener('appUrlOpen', async ({ url }) => {
  await ready
  await handleAppUrl(url)
  if (auth.user) await router.replace(`/workspace/${auth.user.workspace}`)
  else if (pairing.status === 'paired') await router.replace('/sign-in')
  else {
    await router.replace('/connect')
  }
})
