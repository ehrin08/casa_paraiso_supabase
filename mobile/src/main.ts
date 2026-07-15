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
void auth.hydrate().then(() => {
  if (auth.user) void router.replace(`/workspace/${auth.user.workspace}`)
  else if (pairing.status === 'paired') void router.replace('/sign-in')
})

void CapacitorApp.addListener('appUrlOpen', ({ url }) => {
  pairing.acceptDeepLink(url)
})

void CapacitorApp.getLaunchUrl().then((result) => {
  if (result?.url) pairing.acceptDeepLink(result.url)
})
