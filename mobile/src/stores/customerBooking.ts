import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { bookingExpectedAmount } from '../lib/appointments'
import { buildBookingCalendar, monthLabel, shiftMonth } from '../lib/bookingCalendar'
import {
  apiError,
  createCustomerAppointment,
  customerAvailability,
  customerBookingOptions,
  type ApiError,
  type AvailabilitySlot,
  type BookingAvailability,
  type BookingOptions,
  type MobileAppointment,
} from '../lib/api'
import { invalidateMobileData, loadMobileData, REFERENCE_TTL_MS } from '../lib/mobileDataCache'

export const useCustomerBookingStore = defineStore('customerBooking', () => {
  const options = ref<BookingOptions | null>(null)
  const availability = ref<BookingAvailability | null>(null)
  const serviceId = ref<number | null>(null)
  const therapistId = ref<number | null>(null)
  const addonCodes = ref<string[]>([])
  const voucherId = ref<number | null>(null)
  const month = ref('')
  const selectedDate = ref('')
  const selectedSlot = ref<AvailabilitySlot | null>(null)
  const notes = ref('')
  const loading = ref(false)
  const finding = ref(false)
  const submitting = ref(false)
  const error = ref('')
  const fields = ref<Record<string, string[]>>({})

  const selectedService = computed(() => options.value?.services.find(service => service.id === serviceId.value) ?? null)
  const selectedAddons = computed(() => options.value?.addons.filter(addon => addonCodes.value.includes(addon.code)) ?? [])
  const expectedAmount = computed(() => bookingExpectedAmount(
    selectedService.value?.price ?? '0',
    selectedAddons.value.map(addon => addon.price),
  ))
  const availableDates = computed(() => Object.keys(availability.value?.dates ?? {}))
  const slots = computed(() => availability.value?.dates[selectedDate.value] ?? [])
  const calendarDays = computed(() => buildBookingCalendar(month.value, availability.value))
  const calendarMonthLabel = computed(() => monthLabel(month.value))
  let availabilityRequest = 0

  async function loadOptions(preselectedServiceId?: number | null): Promise<void> {
    loading.value = true
    clearError()
    availability.value = null
    therapistId.value = null
    addonCodes.value = []
    voucherId.value = null
    selectedDate.value = ''
    selectedSlot.value = null
    notes.value = ''
    try {
      await loadMobileData('customer:booking-options', REFERENCE_TTL_MS, async () => { options.value = await customerBookingOptions() })
      if (!options.value) throw new Error('Booking options are unavailable.')
      month.value = options.value.booking_window.initial_month
      serviceId.value = options.value.services.some(service => service.id === preselectedServiceId)
        ? preselectedServiceId ?? null
        : options.value.services[0]?.id ?? null
      if (serviceId.value) await findAvailability()
    } catch (reason) {
      capture(reason)
    } finally {
      loading.value = false
    }
  }

  function selectionChanged(): void {
    availability.value = null
    selectedDate.value = ''
    selectedSlot.value = null
    clearError()
    if (options.value && serviceId.value) void findAvailability()
  }

  function serviceChanged(): void {
    therapistId.value = null
    selectionChanged()
  }

  function toggleAddon(code: string): void {
    addonCodes.value = addonCodes.value.includes(code)
      ? addonCodes.value.filter(item => item !== code)
      : [...addonCodes.value, code]
    selectionChanged()
  }

  async function findAvailability(): Promise<void> {
    if (!serviceId.value) { error.value = 'Choose a service first.'; return }
    const request = ++availabilityRequest
    finding.value = true
    clearError()
    try {
      const result = await customerAvailability({
        service_id: serviceId.value,
        preferred_staff_profile_id: therapistId.value ?? undefined,
        promotion_suggestion_id: voucherId.value ?? undefined,
        addon_codes: addonCodes.value,
        month: month.value,
      })
      if (request !== availabilityRequest) return
      availability.value = result
      const dates = Object.keys(availability.value.dates)
      if (selectedDate.value && !availability.value.dates[selectedDate.value]) selectedDate.value = ''
      if (!selectedDate.value) selectedDate.value = dates[0] ?? ''
      if (!slots.value.some(slot => slot.starts_at === selectedSlot.value?.starts_at)) selectedSlot.value = null
    } catch (reason) {
      if (request !== availabilityRequest) return
      capture(reason)
    } finally {
      if (request === availabilityRequest) finding.value = false
    }
  }

  async function book(): Promise<{ appointment: MobileAppointment; message: string } | null> {
    if (!serviceId.value || !selectedSlot.value) { error.value = 'Choose an available appointment time.'; return null }
    submitting.value = true
    clearError()
    try {
      const response = await createCustomerAppointment({
        service_id: serviceId.value,
        preferred_staff_profile_id: therapistId.value ?? undefined,
        promotion_suggestion_id: voucherId.value ?? undefined,
        addon_codes: addonCodes.value,
        requested_start_at: selectedSlot.value.starts_at,
        customer_notes: notes.value.trim() || undefined,
      })
      invalidateMobileData('customer:')
      return { appointment: response.data, message: response.message }
    } catch (reason) {
      capture(reason)
      selectedSlot.value = null
      return null
    } finally {
      submitting.value = false
    }
  }

  function selectDate(date: string): void {
    selectedDate.value = date
    selectedSlot.value = null
  }

  function selectSlot(slot: AvailabilitySlot): void { selectedSlot.value = slot }
  function previousMonth(): void { month.value = shiftMonth(month.value, -1); selectionChanged() }
  function nextMonth(): void { month.value = shiftMonth(month.value, 1); selectionChanged() }
  function clearError(): void { error.value = ''; fields.value = {} }
  function capture(reason: unknown): void {
    const failure: ApiError = apiError(reason)
    error.value = failure.message
    fields.value = failure.fields ?? {}
  }
  function reset(): void { options.value=null;availability.value=null;serviceId.value=null;therapistId.value=null;addonCodes.value=[];voucherId.value=null;month.value='';selectedDate.value='';selectedSlot.value=null;notes.value='';clearError() }

  return {
    options, availability, serviceId, therapistId, addonCodes, voucherId, month, selectedDate, selectedSlot, notes,
    loading, finding, submitting, error, fields, selectedService, selectedAddons, expectedAmount, availableDates, slots, calendarDays, calendarMonthLabel,
    loadOptions, selectionChanged, serviceChanged, toggleAddon, findAvailability, book, selectDate, selectSlot,
    previousMonth, nextMonth, reset,
  }
})
