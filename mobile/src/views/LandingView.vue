<script setup lang="ts">
import { PhArrowRight, PhCalendarBlank, PhCaretRight, PhLeaf, PhSparkle, PhUsersThree } from '@phosphor-icons/vue'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { usePairingStore } from '../stores/pairing'
import { defaultPublicBusinessProfile, loadPublicBusinessProfile } from '../lib/publicBusinessProfile'

const router = useRouter()
const auth = useAuthStore()
const pairing = usePairingStore()
const businessProfile = ref({ ...defaultPublicBusinessProfile })

onMounted(async () => {
  businessProfile.value = await loadPublicBusinessProfile()
})

const treatments = [
  { number: '01', name: 'GAIA TOUCH', duration: '1 Hour', price: '499.00', description: 'Signature Full Body Massage with Swedish, Shiatsu, and Traditional Hilot techniques.', includes: ['Signature Full Body Massage', 'Swedish', 'Shiatsu', 'Traditional Hilot'] },
  { number: '02', name: 'TETHYS FLOW', duration: '1 Hour', price: '649.00', description: 'Signature Full Body Massage with Ventosa or Hot Compress add-on options.', includes: ['Signature Full Body Massage', 'Ventosa', 'Hot Compress'] },
  { number: '03', name: 'HESTIA WARMTH', duration: '1 Hour 30 Minutes', price: '749.00', description: 'Full Body Massage with warming add-on options for deeper body relief.', includes: ['Full Body Massage', 'Ventosa', 'Hot Stone', 'Hot Compress'] },
  { number: '04', name: 'AURORA BREEZE', duration: '2 Hours', price: '849.00', description: 'Extended Full Body Massage package with add-ons and VIP Room access.', includes: ['Full Body Massage', 'Ventosa', 'Hot Compress', 'Hot Stone', 'VIP Room'] },
]

function scrollToSection(id: string): void {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function bookAppointment(serviceId?: number): Promise<void> {
  if (auth.user) {
    await router.push(auth.user.workspace === 'customer' ? { path: '/workspace/customer/appointments', query: serviceId ? { service: String(serviceId) } : {} } : `/workspace/${auth.user.workspace}`)
    return
  }
  if (await pairing.ensurePaired()) await router.push({ path: '/sign-in', query: serviceId ? { service: String(serviceId) } : {} })
}
</script>

<template>
  <main class="landing" id="main-content">
    <header class="landing__header">
      <a class="landing__logo" href="#main-content" aria-label="Casa Paraiso home">
        <img src="/images/casa_paraiso_logo.jpg" alt="Casa Paraiso Body and Wellness Spa">
      </a>
      <nav class="landing__nav" aria-label="Public navigation">
        <button type="button" @click="scrollToSection('treatments')">Treatments</button>
        <button type="button" @click="scrollToSection('how-it-works')">How it works</button>
        <button type="button" @click="scrollToSection('visit')">Visit hours</button>
      </nav>
      <button class="landing__reserve" type="button" @click="bookAppointment()">Login</button>
    </header>

    <section class="landing__hero" aria-labelledby="landing-title">
      <div class="landing__hero-copy">
        <p class="landing__eyebrow">{{ businessProfile.business_name }}</p>
        <h1 id="landing-title">Let the day<br><em>soften here.</em></h1>
        <p class="landing__intro">Thoughtful full-body massage rituals, prepared around your pace and confirmed as soon as your booking succeeds.</p>
        <div class="landing__actions">
          <button class="landing__primary-action" type="button" @click="bookAppointment()">Book an appointment <PhArrowRight :size="20" weight="bold" aria-hidden="true" /></button>
          <button class="landing__secondary-action" type="button" @click="scrollToSection('treatments')">Explore treatments</button>
        </div>
        <p class="landing__quote">“Reserve your spot. You deserve this.”</p>
        <dl class="landing__facts">
          <div><dt>Open</dt><dd>Every day</dd></div>
          <div><dt>Hours</dt><dd>1 PM–12 MN</dd></div>
          <div><dt>From</dt><dd>PHP 499.00</dd></div>
        </dl>
      </div>
      <div class="landing__hero-media">
        <PhLeaf class="landing__leaf" :size="96" weight="thin" aria-hidden="true" />
        <div class="landing__canopy">
          <picture>
            <source media="(max-width: 768px)" srcset="/images/spa/spa-hero-960.webp">
            <img src="/images/spa/spa-hero-1600.webp" alt="A warm linen compress being prepared in a tropical spa treatment room">
          </picture>
        </div>
        <aside class="landing__callout"><p>Confirmed care</p><strong>Choose your preferred visit. A successful booking reserves your time and therapist.</strong></aside>
      </div>
    </section>

    <section class="landing__section landing__section--sand" id="treatments" aria-labelledby="treatments-title">
      <div class="landing__section-heading"><div><p class="landing__eyebrow">Signature treatments</p><h2 id="treatments-title">Four ways to return to yourself.</h2></div><p>Each ritual keeps its time, inclusions, and price clear before you book an appointment. Add-ons can be coordinated with our team before your visit.</p></div>
      <div class="landing__treatments">
        <article v-for="(treatment, index) in treatments" :key="treatment.name" class="landing__treatment-card landing__treatment-card--interactive" tabindex="0" role="button" @click="bookAppointment(index + 1)" @keydown.enter.prevent="bookAppointment(index + 1)" @keydown.space.prevent="bookAppointment(index + 1)">
          <div class="landing__treatment-top"><span>{{ treatment.number }}</span><small>{{ treatment.duration }}</small></div>
          <h3>{{ treatment.name }}</h3><strong>PHP {{ treatment.price }}</strong><p>{{ treatment.description }}</p>
          <ul><li v-for="include in treatment.includes" :key="include">{{ include }}</li></ul><span class="landing__treatment-action">Select this treatment <PhArrowRight :size="17" weight="bold" aria-hidden="true"/></span>
        </article>
      </div>
    </section>

    <section class="landing__section landing__process" id="how-it-works" aria-labelledby="process-title">
      <div class="landing__ritual-image"><picture><source media="(max-width: 768px)" srcset="/images/spa/spa-ritual-800.webp"><img src="/images/spa/spa-ritual-1400.webp" alt="Botanical oil, warm towels, and massage stones being prepared for a spa ritual" loading="lazy"></picture></div>
      <div><p class="landing__eyebrow">A considered booking flow</p><h2 id="process-title">Simple to book.<br>Personal by design.</h2><p class="landing__section-copy">Choose a service, available time, and therapist preference. A successful booking immediately reserves an eligible therapist and schedule.</p>
        <ol class="landing__steps"><li><span>01</span><div><strong>Choose your ritual and preferred time.</strong><p>Available dates and times are shown from active therapist schedules.</p></div></li><li><span>02</span><div><strong>Your visit is confirmed immediately.</strong><p>Your selected time and an eligible therapist are reserved as soon as booking succeeds.</p></div></li><li><span>03</span><div><strong>Return to your account anytime.</strong><p>Your appointment status and wellness history stay organized in one place.</p></div></li></ol>
      </div>
    </section>

    <section class="landing__section landing__section--dark" id="visit" aria-labelledby="visit-title">
      <div><p class="landing__eyebrow">Optional additions</p><h2>Make the ritual your own.</h2><div class="landing__addons"><div v-for="addon in businessProfile.addons" :key="addon[0]"><strong>{{ addon[0] }}</strong><span>{{ addon[1] }}</span></div></div></div>
      <aside class="landing__hours"><p class="landing__eyebrow">Plan your visit</p><h2 id="visit-title">Open every day</h2><strong>1:00 PM to 12:00 MN</strong><hr><p class="landing__eyebrow">Find us</p><p class="landing__address">{{ businessProfile.business_address }}</p><p class="landing__landmarks">{{ businessProfile.location_landmarks }}</p><div class="landing__location-actions"><a :href="businessProfile.messenger_url" target="_blank" rel="noopener noreferrer">Message us</a><a :href="businessProfile.facebook_url" target="_blank" rel="noopener noreferrer">Visit Facebook</a><a :href="businessProfile.map_url" target="_blank" rel="noopener noreferrer">Get directions</a></div><button type="button" @click="bookAppointment()">Book your visit <PhCaretRight :size="18" weight="bold" aria-hidden="true" /></button></aside>
    </section>

    <section class="landing__reassurance" aria-label="Casa Paraiso care promises"><article><PhCalendarBlank :size="28" weight="duotone" aria-hidden="true" /><h2>Clear appointment status</h2><p>Confirmed visits and completed care remain easy to follow.</p></article><article><PhUsersThree :size="28" weight="duotone" aria-hidden="true" /><h2>Therapist-aware scheduling</h2><p>Available therapist schedules are checked as your booking is confirmed.</p></article><article><PhSparkle :size="28" weight="duotone" aria-hidden="true" /><h2>Care that keeps listening</h2><p>Completed visits can be reviewed through thoughtful service feedback.</p></article></section>

    <footer class="landing__footer"><img src="/images/casa_paraiso_logo.jpg" alt="Casa Paraiso Body and Wellness Spa"><div><strong>Open every day · 1:00 PM to 12:00 MN</strong><p>{{ businessProfile.business_address }}</p><a :href="businessProfile.map_url" target="_blank" rel="noopener noreferrer">Get directions</a></div></footer>
  </main>
</template>
