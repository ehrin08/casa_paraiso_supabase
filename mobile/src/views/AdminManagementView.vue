<script setup lang="ts">
import { ref } from 'vue'
import MobileSegmentedControl from '../components/MobileSegmentedControl.vue'
import AdminRosterView from './AdminRosterView.vue'
import AdminServicesView from './AdminServicesView.vue'
import AdminStaffView from './AdminStaffView.vue'
const tab = ref<'staff' | 'services' | 'roster'>('staff')
const items = [{ id: 'staff', label: 'Therapists' }, { id: 'services', label: 'Services' }, { id: 'roster', label: 'Roster' }]
</script>
<template><section><MobileSegmentedControl label="Management areas" :active-id="tab" :items="items" @select="tab=$event as typeof tab"/><AdminStaffView v-if="tab==='staff'"/><AdminServicesView v-else-if="tab==='services'"/><AdminRosterView v-else/></section></template>
<style scoped>:deep(.page){padding-top:1.25rem!important}</style>
