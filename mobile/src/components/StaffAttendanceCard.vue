<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { scanAttendanceQr } from '../lib/attendanceScanner'
import { staffAttendance, submitAttendanceScan, type AttendanceRecord } from '../lib/api'

const attendance = ref<AttendanceRecord | null>(null)
const error = ref('')
const notice = ref('')
const working = ref(false)

async function load(): Promise<void> {
  try {
    attendance.value = (await staffAttendance()).attendance
  } catch {
    error.value = 'Attendance status could not load.'
  }
}

async function scan(): Promise<void> {
  working.value = true
  error.value = ''
  notice.value = ''
  try {
    const result = await submitAttendanceScan(await scanAttendanceQr())
    attendance.value = result.data
    notice.value = result.message
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Attendance scan failed. Please ask an administrator for help.'
  } finally {
    working.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="attendance">
    <h2>Attendance</h2>
    <p v-if="attendance">
      {{ attendance.time_in_at ? `Time in: ${new Date(attendance.time_in_at).toLocaleTimeString()}` : 'Not clocked in' }}
      <span v-if="attendance.time_out_at"> · Time out: {{ new Date(attendance.time_out_at).toLocaleTimeString() }}</span>
    </p>
    <p v-else>Not clocked in today.</p>
    <p v-if="notice" class="notice">{{ notice }}</p>
    <p v-if="error" class="alert">{{ error }}</p>
    <button :disabled="working || attendance?.status === 'closed'" @click="scan">
      {{ working ? 'Opening camera…' : 'Scan attendance QR' }}
    </button>
    <small>A valid live code records your time in or time out immediately.</small>
  </section>
</template>

<style scoped>
.attendance{display:grid;gap:.55rem;margin:1rem 0;padding:1rem;border:1px solid var(--casa-border);border-radius:1rem;background:var(--casa-paper)}
h2{margin:0;font-family:var(--font-display);color:var(--casa-deep-palm)}
button{min-height:48px;border:0;border-radius:.7rem;background:var(--casa-deep-palm);color:white;font-weight:800}
small{color:var(--casa-muted)}
</style>
