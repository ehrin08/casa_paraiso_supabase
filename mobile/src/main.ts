import { App as CapacitorApp } from '@capacitor/app'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { usePairingStore } from './stores/pairing'
import './style.css'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.mount('#app')

const pairing = usePairingStore(pinia)
void pairing.hydrate()

void CapacitorApp.addListener('appUrlOpen', ({ url }) => {
  pairing.acceptDeepLink(url)
})

void CapacitorApp.getLaunchUrl().then((result) => {
  if (result?.url) pairing.acceptDeepLink(result.url)
})
