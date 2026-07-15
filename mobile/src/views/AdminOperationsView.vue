<script setup lang="ts">
import { ref } from 'vue'
import MobileSegmentedControl from '../components/MobileSegmentedControl.vue'
import { useReceptionStore } from '../stores/reception'
import ReceptionAppointmentsView from './ReceptionAppointmentsView.vue'
import ReceptionCustomersView from './ReceptionCustomersView.vue'
import ReceptionPaymentsView from './ReceptionPaymentsView.vue'
const tab=ref<'appointments'|'customers'|'payments'>('appointments'); const operations=useReceptionStore(); operations.configurePrefix('admin')
const items=[{id:'appointments',label:'Bookings'},{id:'customers',label:'Customers'},{id:'payments',label:'Payments'}]
</script>
<template><section><MobileSegmentedControl label="Admin operations" :active-id="tab" :items="items" @select="tab=$event as typeof tab"/><ReceptionAppointmentsView v-if="tab==='appointments'"/><ReceptionCustomersView v-else-if="tab==='customers'"/><ReceptionPaymentsView v-else/></section></template>
<style scoped>:deep(.page){padding-top:1.25rem!important}</style>
