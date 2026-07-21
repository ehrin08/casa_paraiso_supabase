<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import MobileSegmentedControl from '../components/MobileSegmentedControl.vue'
import AdminRosterView from './AdminRosterView.vue'
import AdminServicesView from './AdminServicesView.vue'
import AdminAddonsView from './AdminAddonsView.vue'
import AdminStaffView from './AdminStaffView.vue'
const route = useRoute(); const router = useRouter()
const tab = computed(() => ['staff', 'services', 'addons', 'roster'].includes(String(route.query.manage)) ? String(route.query.manage) : 'staff')
const items = [{ id: 'staff', label: 'Therapists' }, { id: 'services', label: 'Services' }, { id: 'addons', label: 'Add-ons' }, { id: 'roster', label: 'Roster' }]
</script>
<template><section><MobileSegmentedControl label="Management areas" :active-id="tab" :items="items" @select="router.replace({query:{...route.query,manage:$event}})"/><KeepAlive><AdminStaffView v-if="tab==='staff'"/><AdminServicesView v-else-if="tab==='services'"/><AdminAddonsView v-else-if="tab==='addons'"/><AdminRosterView v-else/></KeepAlive></section></template>
<style scoped>:deep(.page){padding-top:1.25rem!important}</style>
