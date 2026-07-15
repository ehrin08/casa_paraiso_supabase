import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  adminCommissions, adminFeedback, adminPromotions, adminReports, adminSettings, adminUsers, apiError,
  createAdminUser, dismissAdminPromotion, payAdminCommission, updateAdminPromotionSettings, updateAdminSettings, updateAdminUser,
  type AdminCommission, type AdminPromotion, type AdminPromotionPreset, type AdminReport, type AdminSettings, type AdminUserAccess, type AppointmentParty, type StaffFeedback,
} from '../lib/api'
import { shareAdminReport } from '../lib/reportExport'
const emptyMeta=()=>({current_page:1,last_page:1,per_page:15,total:0,from:null as number|null,to:null as number|null})
export const useAdminInsightsStore=defineStore('adminInsights',()=>{
  const feedback=ref<StaffFeedback[]>([]);const feedbackSummary=ref({positive:0,neutral:0,negative:0});const feedbackMeta=ref(emptyMeta());const feedbackSentiment=ref('');const feedbackSearch=ref('')
  const commissions=ref<AdminCommission[]>([]);const commissionSummary=ref({pending:'0.00',paid:'0.00',net:'0.00'});const commissionStaff=ref<AppointmentParty[]>([]);const commissionMeta=ref(emptyMeta());const commissionStatus=ref('');const commissionStaffId=ref<number|undefined>()
  const promotions=ref<AdminPromotion[]>([]);const promotionSummary=ref({available:0,reserved:0,used:0,expired:0,dismissed:0});const promotionMeta=ref(emptyMeta());const promotionLifecycle=ref('');const promotionSearch=ref('');const promotionPresets=ref<AdminPromotionPreset[]>([]);const promotionAddons=ref<Array<{code:string;name:string}>>([]);const promotionValidity=ref<number|null>(null);const promotionValidityOptions=ref<number[]>([])
  const report=ref<AdminReport|null>(null);const reportType=ref('appointments');const reportSearch=ref('');const reportDateFrom=ref('');const reportDateTo=ref('')
  const settings=ref<AdminSettings|null>(null);const users=ref<AdminUserAccess[]>([]);const userRoles=ref<string[]>([]);const userMeta=ref(emptyMeta())
  const loading=ref(false);const working=ref(false);const error=ref('');const notice=ref('');const fields=ref<Record<string,string[]>>({})
  async function loadFeedback(page=1):Promise<void>{await run(async()=>{const r=await adminFeedback({page,sentiment:feedbackSentiment.value||undefined,q:feedbackSearch.value.trim()||undefined});feedback.value=r.data;feedbackSummary.value=r.summary;feedbackMeta.value=r.meta})}
  async function loadCommissions(page=1):Promise<void>{await run(async()=>{const r=await adminCommissions({page,status:commissionStatus.value||undefined,staff_profile_id:commissionStaffId.value});commissions.value=r.data;commissionSummary.value=r.summary;commissionStaff.value=r.staff;commissionMeta.value=r.meta})}
  async function payCommission(id:number,paid_at:string,notes=''):Promise<boolean>{return mutate(()=>payAdminCommission(id,{paid_at,notes}),()=>loadCommissions(commissionMeta.value.current_page))}
  async function loadPromotions(page=1):Promise<void>{await run(async()=>{const r=await adminPromotions({page,lifecycle:promotionLifecycle.value||undefined,q:promotionSearch.value.trim()||undefined});promotions.value=r.data;promotionSummary.value=r.summary;promotionMeta.value=r.meta;promotionPresets.value=r.presets;promotionAddons.value=r.addons;promotionValidity.value=r.settings.promotion_voucher_validity_days;promotionValidityOptions.value=r.settings.validity_options})}
  async function dismissPromotion(id:number):Promise<boolean>{return mutate(()=>dismissAdminPromotion(id),()=>loadPromotions(promotionMeta.value.current_page))}
  async function savePromotionSettings(payload:Record<string,unknown>):Promise<boolean>{return mutate(()=>updateAdminPromotionSettings(payload),()=>loadPromotions(1))}
  async function loadReport(page=1):Promise<void>{await run(async()=>{report.value=await adminReports({page,type:reportType.value,q:reportSearch.value.trim()||undefined,date_from:reportDateFrom.value||undefined,date_to:reportDateTo.value||undefined})})}
  async function shareReport():Promise<boolean>{working.value=true;clear();try{const filename=await shareAdminReport(reportType.value,{q:reportSearch.value.trim()||undefined,date_from:reportDateFrom.value||undefined,date_to:reportDateTo.value||undefined});notice.value=`${filename} is ready to share.`;return true}catch(reason){capture(reason);return false}finally{working.value=false}}
  async function loadSettings():Promise<void>{await run(async()=>{settings.value=await adminSettings()})}
  async function saveSettings(payload:Record<string,unknown>):Promise<boolean>{return mutate(()=>updateAdminSettings(payload),loadSettings)}
  async function loadUsers(page=1):Promise<void>{await run(async()=>{const r=await adminUsers(page);users.value=r.data;userRoles.value=r.roles;userMeta.value=r.meta})}
  async function saveUser(payload:Record<string,unknown>,id?:number):Promise<boolean>{return mutate(()=>id?updateAdminUser(id,payload):createAdminUser(payload),()=>loadUsers(id?userMeta.value.current_page:1))}
  async function run(task:()=>Promise<void>):Promise<void>{loading.value=true;clear();try{await task()}catch(reason){capture(reason)}finally{loading.value=false}}
  async function mutate(task:()=>Promise<{message:string}>,refresh:()=>Promise<void>):Promise<boolean>{working.value=true;clear();try{const r=await task();await refresh();notice.value=r.message;return true}catch(reason){capture(reason);return false}finally{working.value=false}}
  function clear():void{error.value='';notice.value='';fields.value={}}
  function capture(reason:unknown):void{const failure=apiError(reason);error.value=failure.message;fields.value=failure.fields??{}}
  return{feedback,feedbackSummary,feedbackMeta,feedbackSentiment,feedbackSearch,commissions,commissionSummary,commissionStaff,commissionMeta,commissionStatus,commissionStaffId,promotions,promotionSummary,promotionMeta,promotionLifecycle,promotionSearch,promotionPresets,promotionAddons,promotionValidity,promotionValidityOptions,report,reportType,reportSearch,reportDateFrom,reportDateTo,settings,users,userRoles,userMeta,loading,working,error,notice,fields,loadFeedback,loadCommissions,payCommission,loadPromotions,dismissPromotion,savePromotionSettings,loadReport,shareReport,loadSettings,saveSettings,loadUsers,saveUser,clear}
})
