<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { formatPeso } from '../lib/appointments'
import { useCustomerBookingStore } from '../stores/customerBooking'
import MobileModalSheet from '../components/MobileModalSheet.vue'

const emit = defineEmits<{ close: []; booked: [message: string] }>()
const props = defineProps<{ serviceId?: number | null }>()
const booking = useCustomerBookingStore()
const step = ref(1)
const stepLabels = ['Treatment', 'Preferences', 'Time', 'Review']
const canContinue = computed(() => step.value === 1 ? !!booking.serviceId : step.value === 3 ? !!booking.selectedSlot : true)

onMounted(() => booking.loadOptions(props.serviceId))

async function submit(): Promise<void> {
  const result = await booking.book()
  if (result) emit('booked', result.message)
}

async function moveAvailableDate(date: string | null, amount: number): Promise<void> {
  if (!date) return
  const currentIndex = booking.calendarDays.findIndex(day => day.date === date)
  const target = booking.calendarDays[currentIndex + amount]
  if (!target?.available || !target.date) return
  booking.selectDate(target.date)
  await nextTick()
  document.querySelector<HTMLButtonElement>(`[data-booking-calendar-day="${target.date}"]`)?.focus()
}
function next(): void { if (canContinue.value && step.value < 4) step.value += 1 }
function back(): void { if (step.value > 1) step.value -= 1 }
</script>

<template>
  <MobileModalSheet :open="true" title="Book an appointment" eyebrow="Reserve your spot" labelled-by="booking-title" @close="emit('close')">

    <div v-if="booking.loading" class="booking-loading" role="status">Preparing services and rewards…</div>
    <form v-else-if="booking.options" class="booking-form" @submit.prevent="submit">
      <p v-if="booking.error" class="alert" role="alert">{{ booking.error }}</p>
      <ol class="booking-progress" aria-label="Booking progress"><li v-for="(label, index) in stepLabels" :key="label" :class="{ active: step === index + 1, complete: step > index + 1 }"><span>{{ index + 1 }}</span>{{ label }}</li></ol>

      <fieldset v-if="step === 1">
        <legend>1. Choose your treatment</legend>
        <label v-for="service in booking.options.services" :key="service.id" class="choice-card" :class="{ selected: booking.serviceId === service.id }">
          <input v-model="booking.serviceId" type="radio" name="service" :value="service.id" @change="booking.serviceChanged">
          <span><strong>{{ service.name }}</strong><small>{{ service.duration_minutes }} min · {{ formatPeso(service.price) }}</small><small>{{ service.description }}</small></span>
        </label>
      </fieldset>

      <template v-if="step === 2">
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
      </template>

      <fieldset v-if="step === 3" class="calendar-fieldset" :aria-busy="booking.finding">
        <legend>{{ booking.options.vouchers.length ? '5' : '4' }}. Choose your time</legend>
        <p class="calendar-intro">Highlighted dates have at least one open 30-minute start time within 1:00 PM to 12:00 midnight.</p>
        <div class="calendar-controls">
          <button type="button" class="calendar-nav" aria-label="Previous month" :disabled="booking.finding" @click="booking.previousMonth">‹</button>
          <h3>{{ booking.calendarMonthLabel }}</h3>
          <button type="button" class="calendar-nav" aria-label="Next month" :disabled="booking.finding" @click="booking.nextMonth">›</button>
        </div>
        <div class="booking-calendar" role="grid" aria-label="Available appointment dates">
          <span v-for="weekday in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="weekday" class="calendar-weekday">{{ weekday }}</span>
          <button v-for="day in booking.calendarDays" :key="day.key" type="button" class="calendar-day" :class="{ available: day.available, selected: day.date === booking.selectedDate, blank: !day.date }" :disabled="!day.available" :tabindex="!day.available ? -1 : (day.date === booking.selectedDate ? 0 : -1)" :data-booking-calendar-day="day.date ?? ''" :aria-label="day.date ? `${day.date}${day.available ? ', available' : ', unavailable'}` : 'Blank calendar day'" role="gridcell" @click="booking.selectDate(day.date!)" @keydown.right.prevent="moveAvailableDate(day.date, 1)" @keydown.left.prevent="moveAvailableDate(day.date, -1)" @keydown.down.prevent="moveAvailableDate(day.date, 7)" @keydown.up.prevent="moveAvailableDate(day.date, -7)">
            <span>{{ day.label }}</span>
            <span v-if="day.available" class="calendar-previews"><small v-for="slot in day.previewSlots" :key="slot.starts_at">{{ slot.label }}</small><small v-if="day.moreSlots">+{{ day.moreSlots }} more</small></span>
          </button>
        </div>
        <p v-if="booking.finding" class="calendar-status" role="status">Checking schedules…</p>
        <p v-else-if="booking.availability && !booking.availableDates.length" class="empty-inline">No times are available for these choices. Try another therapist, add-on, or month.</p>
        <div v-else-if="booking.selectedDate" class="selected-times">
          <p>Available times for {{ booking.selectedDate }}</p>
          <div class="slot-grid">
            <button v-for="slot in booking.slots" :key="slot.starts_at" type="button" :class="{ selected: booking.selectedSlot?.starts_at === slot.starts_at }" @click="booking.selectSlot(slot)">
              <strong>{{ slot.label }}</strong><small>{{ slot.staff_count }} therapist{{ slot.staff_count === 1 ? '' : 's' }}</small>
            </button>
          </div>
        </div>
      </fieldset>

      <template v-if="step === 4">
      <section class="booking-review" aria-label="Booking review"><p class="eyebrow">Review your visit</p><strong>{{ booking.selectedService?.name }}</strong><span>{{ booking.selectedSlot?.label }} · {{ booking.selectedDate }}</span><span>{{ booking.therapistId ? booking.selectedService?.therapists.find(item => item.id === booking.therapistId)?.name : 'Any available therapist' }}</span></section>
      <label>Your note (optional)<textarea v-model="booking.notes" class="field notes" maxlength="5000" placeholder="Share comfort preferences or areas to focus on."></textarea></label>

      <aside class="booking-total">
        <span>Expected visit total</span><strong>{{ formatPeso(booking.expectedAmount) }}</strong>
        <small>Complimentary rewards do not change the package price.</small>
      </aside>
      </template>

      <div class="booking-actions"><button v-if="step > 1" type="button" class="secondary" @click="back">Back</button><button v-if="step < 4" type="button" class="primary" :disabled="!canContinue" @click="next">Continue</button><button v-else class="primary confirm-button" :disabled="booking.submitting || !booking.selectedSlot">{{ booking.submitting ? 'Confirming…' : 'Confirm appointment' }}</button></div>
    </form>
    <p v-else-if="booking.error" class="alert" role="alert">{{ booking.error }}</p>
  </MobileModalSheet>
</template>

<style scoped>
.booking-sheet { position: fixed; z-index: 30; inset: 0; overflow-y: auto; padding: max(1rem, env(safe-area-inset-top)) 1rem max(2rem, env(safe-area-inset-bottom)); background: #f5f0e7; }
.booking-header { position: sticky; z-index: 2; top: calc(-1 * max(1rem, env(safe-area-inset-top))); display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: calc(-1 * max(1rem, env(safe-area-inset-top))) -1rem 1rem; padding: max(1rem, env(safe-area-inset-top)) 1rem 1rem; border-bottom: 1px solid #dcd2c2; background: rgb(255 252 247 / 96%); backdrop-filter: blur(12px); }.booking-header h2 { margin: .2rem 0 0; font-family: Georgia, serif; color: #334736; }.booking-header .eyebrow { margin: 0; }.close-button { width: 48px; height: 48px; border: 1px solid #dcd2c2; border-radius: 999px; background: #fff; color: #334736; font-size: 1.6rem; }
.booking-loading { padding: 3rem 1rem; text-align: center; }.booking-form { width: min(100%, 40rem); margin: 0 auto; display: grid; gap: 1rem; }.booking-form fieldset { display: grid; gap: .65rem; margin: 0; padding: 1rem; border: 1px solid #dcd2c2; border-radius: 1rem; background: #fffcf7; }.booking-form legend { padding: 0 .35rem; color: #334736; font-size: 1rem; font-weight: 800; }
.booking-progress{display:grid;grid-template-columns:repeat(4,1fr);gap:.3rem;margin:0;padding:0;list-style:none}.booking-progress li{display:grid;justify-items:center;gap:.2rem;color:var(--casa-muted);font-size:.62rem;font-weight:800;text-align:center}.booking-progress span{display:grid;width:1.65rem;height:1.65rem;place-items:center;border:1px solid var(--casa-border);border-radius:999px;background:#fff}.booking-progress .active,.booking-progress .complete{color:var(--casa-deep-palm)}.booking-progress .active span,.booking-progress .complete span{color:#fff;border-color:var(--casa-palm);background:var(--casa-palm)}.booking-review{display:grid;gap:.35rem;padding:1rem;border:1px solid var(--casa-palm);border-radius:1rem;background:var(--casa-success-bg)}.booking-review span{color:var(--casa-muted);font-size:.86rem}.booking-actions{display:grid;grid-template-columns:repeat(2,1fr);gap:.65rem}.booking-actions .primary:only-child{grid-column:1/-1}
.choice-card,.check-card { min-height: 56px; display: grid; grid-template-columns: 24px 1fr; align-items: center; gap: .7rem; padding: .7rem; border: 1px solid #dcd2c2; border-radius: .8rem; background: #fff; }.choice-card.selected { border-color: #4f6a4e; background: #eef4ed; }.choice-card input,.check-card input { width: 20px; height: 20px; accent-color: #4f6a4e; }.choice-card span,.check-card span { display: grid; gap: .15rem; }.choice-card small,.check-card small,fieldset > small { color: #67675f; line-height: 1.35; }
.calendar-fieldset { gap: .8rem !important; }.calendar-intro,.calendar-status { margin: 0; color: #67675f; font-size: .875rem; line-height: 1.4; }.calendar-controls { display: grid; grid-template-columns: 48px 1fr 48px; align-items: center; gap: .5rem; }.calendar-controls h3 { margin: 0; color: #334736; font-family: Georgia, serif; font-size: 1.2rem; text-align: center; }.calendar-nav { min-height: 48px; border: 1px solid #dcd2c2; border-radius: 999px; background: #fff; color: #334736; font-size: 1.75rem; line-height: 1; }.calendar-nav:disabled { opacity: .55; }.booking-calendar { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .28rem; }.calendar-weekday { overflow: hidden; color: #67675f; font-size: .66rem; font-weight: 800; text-align: center; text-transform: uppercase; }.calendar-day { min-height: 64px; display: grid; align-content: start; gap: .18rem; border: 1px solid #e2dbd0; border-radius: .65rem; padding: .35rem .18rem; background: #f7f3ec; color: #9c978d; font: inherit; font-size: .76rem; font-weight: 800; text-align: center; }.calendar-day.available { border-color: #b6c9b5; background: #eef4ed; color: #334736; }.calendar-day.selected { border-color: #4f6a4e; background: #4f6a4e; color: #fff; box-shadow: 0 0 0 2px rgb(79 106 78 / 18%); }.calendar-day.blank { visibility: hidden; }.calendar-previews { display: grid; gap: .12rem; }.calendar-previews small { overflow: hidden; border-radius: .25rem; padding: .08rem; background: rgb(79 106 78 / 12%); color: #334736; font-size: .57rem; line-height: 1.15; text-overflow: ellipsis; white-space: nowrap; }.calendar-day.selected .calendar-previews small { background: rgb(255 255 255 / 16%); color: #fff; }.empty-inline { margin: 0; padding: .75rem; border-radius: .75rem; background: #f3ebdd; }.selected-times { display: grid; gap: .55rem; }.selected-times p { margin: 0; color: #334736; font-size: .88rem; font-weight: 800; }
.slot-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .55rem; }.slot-grid button { min-height: 58px; display: grid; place-items: center; gap: .1rem; border: 1px solid #dcd2c2; border-radius: .75rem; background: #fff; color: #334736; }.slot-grid button.selected { border-color: #4f6a4e; outline: 3px solid rgb(79 106 78 / 18%); background: #eef4ed; }.slot-grid small { color: #67675f; }
.notes { min-height: 96px; resize: vertical; }.booking-total { display: grid; grid-template-columns: 1fr auto; gap: .2rem .75rem; padding: 1rem; border-radius: 1rem; background: #334736; color: #fff; }.booking-total strong { font-size: 1.15rem; }.booking-total small { grid-column: 1 / -1; color: #dce8dc; }.confirm-button { position: sticky; bottom: max(.5rem, env(safe-area-inset-bottom)); box-shadow: 0 8px 24px rgb(35 38 32 / 18%); }
@media (min-width: 640px) { .booking-sheet { padding-inline: 1.5rem; }.slot-grid { grid-template-columns: repeat(3, 1fr); } }
</style>
