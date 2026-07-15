<script setup lang="ts">
import { ref } from 'vue'
import { useReceptionStore } from '../stores/reception'
import ReceptionAppointmentsView from './ReceptionAppointmentsView.vue'
import ReceptionCustomersView from './ReceptionCustomersView.vue'
import ReceptionPaymentsView from './ReceptionPaymentsView.vue'
const tab=ref<'appointments'|'customers'|'payments'>('appointments');const operations=useReceptionStore();operations.configurePrefix('admin')
</script>
<template><section><nav class="switch" aria-label="Admin operations"><button :class="{active:tab==='appointments'}" @click="tab='appointments'">Bookings</button><button :class="{active:tab==='customers'}" @click="tab='customers'">Customers</button><button :class="{active:tab==='payments'}" @click="tab='payments'">Payments</button></nav><ReceptionAppointmentsView v-if="tab==='appointments'"/><ReceptionCustomersView v-else-if="tab==='customers'"/><ReceptionPaymentsView v-else/></section></template>
<style scoped>.switch{position:fixed;z-index:14;top:max(3.65rem,calc(env(safe-area-inset-top) + 3rem));left:50%;display:grid;grid-template-columns:repeat(3,1fr);width:min(calc(100% - 2rem),31rem);padding:.25rem;border:1px solid #dcd2c2;border-radius:.8rem;background:#fffcf7;transform:translateX(-50%);box-shadow:0 4px 14px rgb(35 38 32 / 8%)}.switch button{min-height:40px;border:0;border-radius:.6rem;background:transparent;color:#67675f;font-weight:800}.switch button.active{background:#e9f2e8;color:#334736}:deep(.page){padding-top:max(8.2rem,calc(env(safe-area-inset-top) + 7.5rem))}</style>
