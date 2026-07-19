<script setup lang="ts">
import { onMounted, reactive, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useCustomerProfileStore } from '../stores/customerProfile'
import MobileSkeleton from '../components/MobileSkeleton.vue'
import { useInitialLoad } from '../composables/useInitialLoad'

const profileStore = useCustomerProfileStore()
const auth = useAuthStore()
const router = useRouter()
const form = reactive({ name: '', phone: '', address: '', contact_preference: '' })
const password = reactive({ current: '', next: '', confirmation: '' })
const { initialLoading, loadInitial } = useInitialLoad()

watch(() => profileStore.profile, (value) => {
  if (!value) return
  form.name = value.name; form.phone = value.phone ?? ''; form.address = value.address ?? ''; form.contact_preference = value.contact_preference ?? ''
}, { immediate: true })
onMounted(() => void loadInitial(() => profileStore.load()))

async function save(): Promise<void> {
  if (await profileStore.save(form) && profileStore.profile) auth.applyProfile(profileStore.profile.name, profileStore.profile.phone)
}
async function changePassword(): Promise<void> {
  const message = await profileStore.changePassword(password.current, password.next, password.confirmation)
  if (!message) return
  await auth.clear()
  await router.replace({ path: '/sign-in', query: { notice: message } })
}
async function leave(): Promise<void> { if (await auth.signOut()) await router.replace('/sign-in') }
</script>

<template>
  <section class="customer-page" aria-labelledby="profile-title">
    <header><p class="eyebrow">Your account</p><h1 id="profile-title">Profile</h1><p>Keep your contact details current for smooth appointment coordination.</p></header>
    <p v-if="profileStore.error" class="alert" role="alert">{{ Object.values(profileStore.fields).flat()[0] ?? profileStore.error }}</p>
    <p v-if="profileStore.notice" class="notice" role="status">{{ profileStore.notice }}</p>
    <MobileSkeleton v-if="initialLoading" variant="form" label="Loading your profile" />

    <template v-else-if="profileStore.profile">
      <form class="profile-card" @submit.prevent="save">
        <div class="identity"><span>Customer code</span><strong>{{ profileStore.profile.customer_code }}</strong></div>
        <label><span>Name</span><input v-model="form.name" required maxlength="255" autocomplete="name"></label>
        <label><span>Email</span><input :value="profileStore.profile.email" disabled autocomplete="email"><small>Email changes require account verification and are not available in the app.</small></label>
        <label><span>Phone</span><input v-model="form.phone" maxlength="50" autocomplete="tel" inputmode="tel"></label>
        <label><span>Address</span><textarea v-model="form.address" maxlength="2000" rows="4" autocomplete="street-address"></textarea></label>
        <label><span>Preferred contact</span><select v-model="form.contact_preference"><option value="">No preference</option><option v-for="option in profileStore.profile.contact_preferences" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
        <button class="primary" :disabled="profileStore.saving">{{ profileStore.saving ? 'Saving…' : 'Save profile' }}</button>
      </form>

      <form v-if="profileStore.profile.has_password" class="profile-card" @submit.prevent="changePassword">
        <div><p class="eyebrow">Account security</p><h2>Change password</h2><p>You will sign in again after a successful password change.</p></div>
        <label><span>Current password</span><input v-model="password.current" type="password" required autocomplete="current-password"></label>
        <label><span>New password</span><input v-model="password.next" type="password" required autocomplete="new-password"></label>
        <label><span>Confirm new password</span><input v-model="password.confirmation" type="password" required autocomplete="new-password"></label>
        <button class="secondary" :disabled="profileStore.changingPassword">{{ profileStore.changingPassword ? 'Updating…' : 'Update password' }}</button>
      </form>
      <section v-else class="profile-card"><p class="eyebrow">Account security</p><h2>Password setup</h2><p>Reconfirm your linked Google identity in the browser account settings to create your first password. Mobile Google confirmation is part of the upcoming secure sign-in milestone.</p></section>

      <button class="sign-out" :disabled="auth.working" @click="leave">{{ auth.working ? 'Signing out…' : 'Sign out of this phone' }}</button>
    </template>
  </section>
</template>

<style scoped>
.customer-page { width:min(100%,42rem); margin:0 auto; padding:max(1.25rem,env(safe-area-inset-top)) 1rem calc(6rem + env(safe-area-inset-bottom)) }.customer-page header h1,.profile-card h2 { font-family:Georgia,serif; color:#334736 }.customer-page header p { margin:.25rem 0 }.notice { padding:.75rem; border-radius:.75rem; background:#e9f2e8; color:#334736 }.loading { padding:2rem; text-align:center; color:#67675f }.profile-card { display:grid; gap:1rem; margin-top:1rem; padding:1rem; border:1px solid #dcd2c2; border-radius:1rem; background:#fffcf7; box-shadow:0 6px 18px rgb(35 38 32 / 6%) }.identity { display:flex; justify-content:space-between; gap:1rem; padding:.75rem; border-radius:.75rem; background:#f3ebdd; color:#334736 }.profile-card label>span { display:block; margin-bottom:.35rem; font-weight:700 }.profile-card input,.profile-card textarea,.profile-card select { width:100%; min-height:48px; border:1px solid #bfb3a2; border-radius:.75rem; background:#fff; padding:.7rem .75rem; color:#232620; font:inherit }.profile-card textarea { min-height:96px; resize:vertical }.profile-card input:disabled { background:#eeeae2; color:#67675f }.profile-card label small { display:block; margin-top:.35rem; color:#67675f }.profile-card .primary,.profile-card .secondary,.sign-out { min-height:48px; border-radius:.75rem; font:inherit; font-weight:800 }.profile-card .primary { border:0; background:#4f6a4e; color:#fff }.profile-card .secondary { border:1px solid #4f6a4e; background:#e9f2e8; color:#334736 }.sign-out { width:100%; margin-top:1rem; border:1px solid #a04a3c; background:#fff7f5; color:#8c2f26 }.profile-card button:disabled,.sign-out:disabled { opacity:.55 }
</style>
