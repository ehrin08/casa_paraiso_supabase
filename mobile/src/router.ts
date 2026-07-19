import { createRouter, createWebHashHistory } from 'vue-router'
import LandingView from './views/LandingView.vue'
import SignInView from './views/SignInView.vue'
import StartupView from './views/StartupView.vue'
import WorkspaceView from './views/WorkspaceView.vue'

export const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', component: LandingView },
    { path: '/starting', component: StartupView },
    { path: '/sign-in', component: SignInView },
    { path: '/workspace/:workspace', component: WorkspaceView },
  ],
})
