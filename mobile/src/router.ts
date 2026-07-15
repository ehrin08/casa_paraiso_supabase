import { createRouter, createWebHashHistory } from 'vue-router'
import ConnectView from './views/ConnectView.vue'
import SignInView from './views/SignInView.vue'
import WorkspaceView from './views/WorkspaceView.vue'

export const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', redirect: '/connect' },
    { path: '/connect', component: ConnectView },
    { path: '/sign-in', component: SignInView },
    { path: '/workspace/:workspace', component: WorkspaceView },
  ],
})
