import { App as CapacitorApp } from '@capacitor/app'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { usePairingStore } from './stores/pairing'
import { useAuthStore } from './stores/auth'
import { router } from './router'
import './style.css'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(router)
app.mount('#app')

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
