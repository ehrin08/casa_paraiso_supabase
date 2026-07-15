<script setup lang="ts">
import { onMounted } from 'vue'
import { formatBookingDay, formatPeso } from '../lib/appointments'
import { useCustomerBookingStore } from '../stores/customerBooking'
import MobileModalSheet from '../components/MobileModalSheet.vue'

const emit = defineEmits<{ close: []; booked: [message: string] }>()
const booking = useCustomerBookingStore()

onMounted(() => booking.loadOptions())

async function submit(): Promise<void> {
  const result = await booking.book()
  if (result) emit('booked', result.message)
}
</script>

<template>
  <MobileModalSheet :open="true" title="Book an appointment" eyebrow="Reserve your spot" labelled-by="booking-title" @close="emit('close')">

    <div v-if="booking.loading" class="booking-loading" role="status">Preparing services and rewards…</div>
    <form v-else-if="booking.options" class="booking-form" @submit.prevent="submit">
      <p v-if="booking.error" class="alert" role="alert">{{ booking.error }}</p>

      <fieldset>
        <legend>1. Choose your treatment</legend>
        <label v-for="service in booking.options.services" :key="service.id" class="choice-card" :class="{ selected: booking.serviceId === service.id }">
          <input v-model="booking.serviceId" type="radio" name="service" :value="service.id" @change="booking.serviceChanged">
          <span><strong>{{ service.name }}</strong><small>{{ service.duration_minutes }} min · {{ formatPeso(service.price) }}</small><small>{{ service.description }}</small></span>
        </label>
      </fieldset>

      <fieldset v-if="booking.selectedService">
        <legend>2. Therapist preference</legend>
        <select v-model="booking.therapistId" class="field" @change="booking.selectionChanged">
          <option :value="null">Any available therapist</option>
          <option v-for="therapist in booking.selectedService.therapists" :key="therapist.id" :value="therapist.id">{{ therapist.name }}</option>
        </select>
        <small>We will assign the least-booked eligible therapist if your preference is unavailable.</small>
      </fieldset>

      <fieldset>
        <legend>3. Add-ons</legend>
        <label v-for="addon in booking.options.addons" :key="addon.code" class="check-card">
          <input type="checkbox" :checked="booking.addonCodes.includes(addon.code)" :disabled="booking.options.vouchers.some(voucher => voucher.id === booking.voucherId && voucher.code === addon.code)" @change="booking.toggleAddon(addon.code)">
          <span><strong>{{ addon.name }}</strong><small>{{ formatPeso(addon.price) }}<template v-if="addon.duration_minutes"> · adds {{ addon.duration_minutes }} min</template></small></span>
        </label>
      </fieldset>

      <fieldset v-if="booking.options.vouchers.length">
        <legend>4. Complimentary reward</legend>
        <label class="choice-card"><input v-model="booking.voucherId" type="radio" name="voucher" :value="null" @change="booking.selectionChanged"><span><strong>Save my reward</strong><small>Continue without using a voucher.</small></span></label>
        <label v-for="voucher in booking.options.vouchers" :key="voucher.id" class="choice-card" :class="{ selected: booking.voucherId === voucher.id }">
          <input v-model="booking.voucherId" type="radio" name="voucher" :value="voucher.id" :disabled="booking.addonCodes.includes(voucher.code)" @change="booking.selectionChanged">
          <span><strong>Complimentary {{ voucher.name }}</strong><small>{{ voucher.expires_at ? `Valid until ${new Date(voucher.expires_at).toLocaleDateString('en-PH')}` : 'No expiry' }}</small></span>
        </label>
      </fieldset>

      <fieldset>
        <legend>{{ booking.options.vouchers.length ? '5' : '4' }}. Find a time</legend>
        <label>Month<input v-model="booking.month" class="field" type="month" :min="booking.options.booking_window.initial_month" @change="booking.selectionChanged"></label>
        <button type="button" class="secondary-button" :disabled="booking.finding || !booking.serviceId" @click="booking.findAvailability">
          {{ booking.finding ? 'Checking schedules…' : 'Find available times' }}
        </button>
        <p v-if="booking.availability && !booking.availableDates.length" class="empty-inline">No times are available for these choices. Try another therapist, add-on, or month.</p>
      </fieldset>

      <template v-if="booking.availableDates.length">
        <div class="date-strip" role="tablist" aria-label="Available appointment dates" tabindex="0">
          <button v-for="date in booking.availableDates" :key="date" type="button" role="tab" :aria-selected="booking.selectedDate === date" :class="{ active: booking.selectedDate === date }" @click="booking.selectDate(date)">{{ formatBookingDay(date) }}</button>
        </div>
        <fieldset>
          <legend>Available times</legend>
          <div class="slot-grid">
            <button v-for="slot in booking.slots" :key="slot.starts_at" type="button" :class="{ selected: booking.selectedSlot?.starts_at === slot.starts_at }" @click="booking.selectSlot(slot)">
              <strong>{{ slot.label }}</strong><small>{{ slot.staff_count }} therapist{{ slot.staff_count === 1 ? '' : 's' }}</small>
            </button>
          </div>
        </fieldset>
      </template>

      <label>Your note (optional)<textarea v-model="booking.notes" class="field notes" maxlength="5000" placeholder="Share comfort preferences or areas to focus on."></textarea></label>

      <aside class="booking-total">
        <span>Expected visit total</span><strong>{{ formatPeso(booking.expectedAmount) }}</strong>
        <small>Complimentary rewards do not change the package price.</small>
      </aside>

      <button class="primary confirm-button" :disabled="booking.submitting || !booking.selectedSlot">
        {{ booking.submitting ? 'Confirming…' : 'Confirm appointment' }}
      </button>
    </form>
    <p v-else-if="booking.error" class="alert" role="alert">{{ booking.error }}</p>
  </MobileModalSheet>
</template>

<style scoped>
.booking-sheet { position: fixed; z-index: 30; inset: 0; overflow-y: auto; padding: max(1rem, env(safe-area-inset-top)) 1rem max(2rem, env(safe-area-inset-bottom)); background: #f5f0e7; }
.booking-header { position: sticky; z-index: 2; top: calc(-1 * max(1rem, env(safe-area-inset-top))); display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: calc(-1 * max(1rem, env(safe-area-inset-top))) -1rem 1rem; padding: max(1rem, env(safe-area-inset-top)) 1rem 1rem; border-bottom: 1px solid #dcd2c2; background: rgb(255 252 247 / 96%); backdrop-filter: blur(12px); }.booking-header h2 { margin: .2rem 0 0; font-family: Georgia, serif; color: #334736; }.booking-header .eyebrow { margin: 0; }.close-button { width: 48px; height: 48px; border: 1px solid #dcd2c2; border-radius: 999px; background: #fff; color: #334736; font-size: 1.6rem; }
.booking-loading { padding: 3rem 1rem; text-align: center; }.booking-form { width: min(100%, 40rem); margin: 0 auto; display: grid; gap: 1rem; }.booking-form fieldset { display: grid; gap: .65rem; margin: 0; padding: 1rem; border: 1px solid #dcd2c2; border-radius: 1rem; background: #fffcf7; }.booking-form legend { padding: 0 .35rem; color: #334736; font-size: 1rem; font-weight: 800; }
.choice-card,.check-card { min-height: 56px; display: grid; grid-template-columns: 24px 1fr; align-items: center; gap: .7rem; padding: .7rem; border: 1px solid #dcd2c2; border-radius: .8rem; background: #fff; }.choice-card.selected { border-color: #4f6a4e; background: #eef4ed; }.choice-card input,.check-card input { width: 20px; height: 20px; accent-color: #4f6a4e; }.choice-card span,.check-card span { display: grid; gap: .15rem; }.choice-card small,.check-card small,fieldset > small { color: #67675f; line-height: 1.35; }
.secondary-button { min-height: 48px; border: 1px solid #4f6a4e; border-radius: .75rem; background: #fff; color: #334736; font: inherit; font-weight: 800; }.secondary-button:disabled { opacity: .55; }.empty-inline { margin: 0; padding: .75rem; border-radius: .75rem; background: #f3ebdd; }
.date-strip { display: flex; gap: .5rem; overflow-x: auto; padding: .15rem .1rem .5rem; }.date-strip button { min-width: 104px; min-height: 48px; border: 1px solid #dcd2c2; border-radius: 999px; background: #fff; color: #334736; font: inherit; font-weight: 700; }.date-strip button.active { border-color: #4f6a4e; background: #4f6a4e; color: #fff; }
.slot-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .55rem; }.slot-grid button { min-height: 58px; display: grid; place-items: center; gap: .1rem; border: 1px solid #dcd2c2; border-radius: .75rem; background: #fff; color: #334736; }.slot-grid button.selected { border-color: #4f6a4e; outline: 3px solid rgb(79 106 78 / 18%); background: #eef4ed; }.slot-grid small { color: #67675f; }
.notes { min-height: 96px; resize: vertical; }.booking-total { display: grid; grid-template-columns: 1fr auto; gap: .2rem .75rem; padding: 1rem; border-radius: 1rem; background: #334736; color: #fff; }.booking-total strong { font-size: 1.15rem; }.booking-total small { grid-column: 1 / -1; color: #dce8dc; }.confirm-button { position: sticky; bottom: max(.5rem, env(safe-area-inset-bottom)); box-shadow: 0 8px 24px rgb(35 38 32 / 18%); }
@media (min-width: 640px) { .booking-sheet { padding-inline: 1.5rem; }.slot-grid { grid-template-columns: repeat(3, 1fr); } }
</style>
