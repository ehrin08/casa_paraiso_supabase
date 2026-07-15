<script setup lang="ts">
import { ref } from 'vue'
import AdminServicesView from './AdminServicesView.vue'
import AdminStaffView from './AdminStaffView.vue'
import AdminRosterView from './AdminRosterView.vue'
const tab = ref<'staff' | 'services' | 'roster'>('staff')
</script>
<template><section><nav class="switch" aria-label="Management areas"><button :class="{active:tab==='staff'}" @click="tab='staff'">Therapists</button><button :class="{active:tab==='services'}" @click="tab='services'">Services</button><button :class="{active:tab==='roster'}" @click="tab='roster'">Roster</button></nav><AdminStaffView v-if="tab==='staff'"/><AdminServicesView v-else-if="tab==='services'"/><AdminRosterView v-else/></section></template>
<style scoped>.switch{position:fixed;z-index:14;top:max(3.65rem,calc(env(safe-area-inset-top) + 3rem));left:50%;display:grid;grid-template-columns:repeat(3,1fr);width:min(calc(100% - 2rem),28rem);padding:.25rem;border:1px solid #dcd2c2;border-radius:.8rem;background:#fffcf7;transform:translateX(-50%);box-shadow:0 4px 14px rgb(35 38 32 / 8%)}.switch button{min-height:40px;border:0;border-radius:.6rem;background:transparent;color:#67675f;font-weight:800}.switch button.active{background:#e9f2e8;color:#334736}:deep(.page){padding-top:max(8.2rem,calc(env(safe-area-inset-top) + 7.5rem))}</style>
