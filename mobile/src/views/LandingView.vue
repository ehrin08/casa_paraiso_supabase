<script setup lang="ts">
import { PhArrowRight, PhCalendarBlank, PhCaretRight, PhLeaf, PhSparkle, PhUsersThree } from '@phosphor-icons/vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { usePairingStore } from '../stores/pairing'

const router = useRouter()
const auth = useAuthStore()
const pairing = usePairingStore()

const treatments = [
  { number: '01', name: 'GAIA TOUCH', duration: '1 Hour', price: '499.00', description: 'Signature Full Body Massage with Swedish, Shiatsu, and Traditional Hilot techniques.', includes: ['Signature Full Body Massage', 'Swedish', 'Shiatsu', 'Traditional Hilot'] },
  { number: '02', name: 'TETHYS FLOW', duration: '1 Hour', price: '649.00', description: 'Signature Full Body Massage with Ventosa or Hot Compress add-on options.', includes: ['Signature Full Body Massage', 'Ventosa', 'Hot Compress'] },
  { number: '03', name: 'HESTIA WARMTH', duration: '1 Hour 30 Minutes', price: '749.00', description: 'Full Body Massage with warming add-on options for deeper body relief.', includes: ['Full Body Massage', 'Ventosa', 'Hot Stone', 'Hot Compress'] },
  { number: '04', name: 'AURORA BREEZE', duration: '2 Hours', price: '849.00', description: 'Extended Full Body Massage package with add-ons and VIP Room access.', includes: ['Full Body Massage', 'Ventosa', 'Hot Compress', 'Hot Stone', 'VIP Room'] },
]

const addons = [
  ['Ventosa', 'PHP 200.00'], ['Hot Compress', 'PHP 200.00'], ['Hot Stone', 'PHP 200.00'], ['30-Minute Back Massage', 'PHP 299.00'], ['VIP Room', 'PHP 200.00'],
]

function scrollToSection(id: string): void {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function requestAppointment(): Promise<void> {
  if (auth.user) {
    await router.push(`/workspace/${auth.user.workspace}`)
    return
  }
  await router.push(pairing.status === 'paired' ? '/sign-in' : '/starting')
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
      <button class="landing__reserve" type="button" @click="requestAppointment">Reserve</button>
    </header>

    <section class="landing__hero" aria-labelledby="landing-title">
      <div class="landing__hero-copy">
        <p class="landing__eyebrow">Casa Paraiso Body and Wellness Spa</p>
        <h1 id="landing-title">Let the day<br><em>soften here.</em></h1>
        <p class="landing__intro">Thoughtful full-body massage rituals, prepared around your pace and confirmed with care by our spa team.</p>
        <div class="landing__actions">
          <button class="landing__primary-action" type="button" @click="requestAppointment">Request an appointment <PhArrowRight :size="20" weight="bold" aria-hidden="true" /></button>
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
        <aside class="landing__callout"><p>Request-first care</p><strong>Choose your preferred visit. Our team confirms the final schedule.</strong></aside>
      </div>
    </section>

    <section class="landing__section landing__section--sand" id="treatments" aria-labelledby="treatments-title">
      <div class="landing__section-heading"><div><p class="landing__eyebrow">Signature treatments</p><h2 id="treatments-title">Four ways to return to yourself.</h2></div><p>Each ritual keeps its time, inclusions, and price clear before you book an appointment. Add-ons can be coordinated with our team before your visit.</p></div>
      <div class="landing__treatments">
        <article v-for="treatment in treatments" :key="treatment.name" class="landing__treatment-card">
          <div class="landing__treatment-top"><span>{{ treatment.number }}</span><small>{{ treatment.duration }}</small></div>
          <h3>{{ treatment.name }}</h3><strong>PHP {{ treatment.price }}</strong><p>{{ treatment.description }}</p>
          <ul><li v-for="include in treatment.includes" :key="include">{{ include }}</li></ul>
        </article>
      </div>
    </section>

    <section class="landing__section landing__process" id="how-it-works" aria-labelledby="process-title">
      <div class="landing__ritual-image"><picture><source media="(max-width: 768px)" srcset="/images/spa/spa-ritual-800.webp"><img src="/images/spa/spa-ritual-1400.webp" alt="Botanical oil, warm towels, and massage stones being prepared for a spa ritual" loading="lazy"></picture></div>
      <div><p class="landing__eyebrow">A considered booking flow</p><h2 id="process-title">Simple to request.<br>Personal to confirm.</h2><p class="landing__section-copy">A request starts the conversation. Your booking becomes final after the team checks the service, therapist, and schedule.</p>
        <ol class="landing__steps"><li><span>01</span><div><strong>Choose your ritual and preferred time.</strong><p>Available dates and times are shown from active therapist schedules.</p></div></li><li><span>02</span><div><strong>Your visit is confirmed.</strong><p>Booking immediately reserves an eligible therapist and the selected schedule.</p></div></li><li><span>03</span><div><strong>Return to your account for confirmation.</strong><p>Your appointment status and wellness history stay organized in one place.</p></div></li></ol>
      </div>
    </section>

    <section class="landing__section landing__section--dark" id="visit" aria-labelledby="visit-title">
      <div><p class="landing__eyebrow">Optional additions</p><h2>Make the ritual your own.</h2><div class="landing__addons"><div v-for="addon in addons" :key="addon[0]"><strong>{{ addon[0] }}</strong><span>{{ addon[1] }}</span></div></div></div>
      <aside class="landing__hours"><p class="landing__eyebrow">Plan your visit</p><h2 id="visit-title">Open every day</h2><strong>1:00 PM to 12:00 MN</strong><hr><p>Reserve your spot. You deserve this.</p><button type="button" @click="requestAppointment">Request your visit <PhCaretRight :size="18" weight="bold" aria-hidden="true" /></button></aside>
    </section>

    <section class="landing__reassurance" aria-label="Casa Paraiso care promises"><article><PhCalendarBlank :size="28" weight="duotone" aria-hidden="true" /><h2>Clear appointment status</h2><p>Requests, confirmed visits, and completed care remain easy to follow.</p></article><article><PhUsersThree :size="28" weight="duotone" aria-hidden="true" /><h2>Therapist-aware scheduling</h2><p>The spa team checks availability before every booking becomes final.</p></article><article><PhSparkle :size="28" weight="duotone" aria-hidden="true" /><h2>Care that keeps listening</h2><p>Completed visits can be reviewed through thoughtful service feedback.</p></article></section>

    <footer class="landing__footer"><img src="/images/casa_paraiso_logo.jpg" alt="Casa Paraiso Body and Wellness Spa"><div><strong>Open every day · 1:00 PM to 12:00 MN</strong><p>Reservations are confirmed by the Casa Paraiso team.</p></div></footer>
  </main>
</template>
