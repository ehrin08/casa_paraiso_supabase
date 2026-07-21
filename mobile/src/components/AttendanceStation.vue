<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import QrcodeVue from 'qrcode.vue'
import { attendanceQr } from '../lib/api'

const qr = ref<{ payload: string; expires_at: string } | null>(null)
const error = ref('')
const seconds = ref(60)
let timer: ReturnType<typeof setInterval> | undefined

async function refresh(): Promise<void> {
  try {
    qr.value = await attendanceQr()
    seconds.value = Math.max(1, Math.ceil((new Date(qr.value.expires_at).getTime() - Date.now()) / 1000))
  } catch {
    error.value = 'Attendance station could not refresh.'
  }
}

const countdown = computed(() => `${Math.floor(seconds.value / 60)}:${String(seconds.value % 60).padStart(2, '0')}`)

onMounted(async () => {
  await refresh()
  timer = setInterval(async () => {
    if (qr.value) seconds.value = Math.max(0, Math.ceil((new Date(qr.value.expires_at).getTime() - Date.now()) / 1000))
    if (seconds.value === 0) await refresh()
  }, 1_000)
})

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>

<template>
  <section class="station">
    <header>
      <p class="eyebrow">ATTENDANCE STATION</p>
      <h2>Automatic therapist check-in</h2>
      <p>Show this live code. A valid therapist scan records time in or time out immediately.</p>
    </header>
    <p v-if="error" class="alert">{{ error }}</p>
    <div v-if="qr" class="qr">
      <QrcodeVue :value="qr.payload" :size="220" level="H" aria-label="Therapist attendance check-in QR code" />
      <strong>Refreshes in {{ countdown }}</strong>
    </div>
  </section>
</template>

<style scoped>
.station{margin-top:1rem;padding:1rem;border:1px solid var(--casa-border);border-radius:1rem;background:var(--casa-paper)}
h2{font-family:var(--font-display);color:var(--casa-deep-palm)}
.qr{display:grid;justify-items:center;gap:.75rem;padding:1rem;background:#fff;border-radius:.75rem}
</style>
