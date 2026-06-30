<template>
  <div class="max-w-3xl space-y-6">

    <div class="animate-fade-down">
      <h1 class="page-title">Settings</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Manage company profile and preferences</p>
    </div>

    <!-- Company settings -->
    <div class="card p-6 animate-fade-up">
      <div class="flex items-center gap-3 mb-6 pb-5 border-b border-gray-100 dark:border-gray-800">
        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center">
          <BuildingIcon class="w-5 h-5 text-white" />
        </div>
        <div>
          <h2 class="text-base font-bold text-gray-900 dark:text-white">Company Profile</h2>
          <p class="text-xs text-gray-400">Used in GST invoices and reports</p>
        </div>
      </div>

      <div v-if="company" class="space-y-5">

        <!-- Logo upload -->
        <div>
          <label class="label mb-2">Company Logo</label>
          <div class="flex items-center gap-4">
            <div class="relative group cursor-pointer" @click="$refs.logoInput.click()">
              <div class="w-20 h-20 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700 flex items-center justify-center overflow-hidden bg-gray-50 dark:bg-gray-800 hover:border-blue-400 transition-colors">
                <img v-if="logoPreview || company.logo_url"
                     :src="logoPreview || company.logo_url"
                     class="w-full h-full object-contain p-1" alt="Logo" />
                <div v-else class="flex flex-col items-center gap-1 text-gray-300">
                  <ImageIcon class="w-7 h-7" />
                  <span class="text-xs">Logo</span>
                </div>
              </div>
              <div class="absolute inset-0 rounded-2xl bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                <CameraIcon class="w-5 h-5 text-white" />
              </div>
            </div>
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Upload company logo</p>
              <p class="text-xs text-gray-400 mt-0.5">PNG, JPG or SVG · max 2 MB · shown on invoices</p>
              <div class="flex gap-2 mt-2">
                <button class="btn-secondary text-xs py-1.5 px-3" @click="$refs.logoInput.click()">
                  <UploadIcon class="w-3.5 h-3.5" /> Choose File
                </button>
                <button v-if="logoPreview || company.logo_url" class="text-xs text-rose-500 hover:text-rose-700 font-semibold px-2" @click="removeLogo">
                  Remove
                </button>
              </div>
            </div>
            <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="onLogoSelected" />
          </div>
          <!-- Upload progress -->
          <div v-if="logoUploading" class="mt-2 flex items-center gap-2 text-xs text-blue-600">
            <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg> Uploading logo…
          </div>
          <p v-if="logoMsg" class="mt-1.5 text-xs" :class="logoOk ? 'text-emerald-600' : 'text-rose-500'">{{ logoMsg }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Company Name *</label>
            <input v-model="company.name" class="input" placeholder="Demo Traders" />
          </div>
          <div>
            <label class="label">Legal Name</label>
            <input v-model="company.legal_name" class="input" placeholder="Demo Traders Pvt Ltd" />
          </div>
          <div>
            <label class="label">GSTIN</label>
            <input v-model="company.gstin" class="input font-mono" maxlength="15" placeholder="27AABCT1332L1ZS" />
          </div>
          <div>
            <label class="label">PAN</label>
            <input v-model="company.pan" class="input font-mono" maxlength="10" placeholder="AABCT1332L" />
          </div>
          <div>
            <label class="label">Phone</label>
            <input v-model="company.phone" class="input" placeholder="+91 98765 43210" />
          </div>
          <div>
            <label class="label">Email</label>
            <input v-model="company.email" type="email" class="input" placeholder="info@company.com" />
          </div>
          <div>
            <label class="label">City</label>
            <input v-model="company.city" class="input" placeholder="Mumbai" />
          </div>
          <div>
            <label class="label">State</label>
            <input v-model="company.state" class="input" placeholder="Maharashtra" />
          </div>
          <div class="col-span-2">
            <label class="label">Address</label>
            <textarea v-model="company.address" class="input resize-none" rows="2" placeholder="Full business address" />
          </div>
        </div>

        <Transition name="slide-down">
          <div v-if="msg" class="flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold"
               :class="msgType === 'success' ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-900/20 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800'">
            <CheckCircleIcon v-if="msgType === 'success'" class="w-4 h-4 flex-shrink-0" />
            <AlertCircleIcon v-else class="w-4 h-4 flex-shrink-0" />
            {{ msg }}
          </div>
        </Transition>

        <div class="flex items-center gap-3 pt-2">
          <button class="btn-primary" :disabled="saving" @click="save">
            <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            <SaveIcon v-else class="w-4 h-4" />
            {{ saving ? 'Saving…' : 'Save Settings' }}
          </button>
        </div>
      </div>

      <div v-else class="py-8 flex flex-col items-center gap-3 text-gray-400">
        <div class="w-8 h-8 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm">Loading company settings…</p>
      </div>
    </div>

    <!-- App info -->
    <div class="card p-6 animate-fade-up delay-75">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-violet-500 to-violet-700 flex items-center justify-center">
          <InfoIcon class="w-5 h-5 text-white" />
        </div>
        <div>
          <h2 class="text-base font-bold text-gray-900 dark:text-white">About OpenVyapar</h2>
          <p class="text-xs text-gray-400">Open source ERP for Indian businesses</p>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div v-for="info in appInfo" :key="info.label"
             class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50">
          <component :is="info.icon" class="w-4 h-4 text-gray-400 flex-shrink-0" />
          <div>
            <p class="text-xs text-gray-400">{{ info.label }}</p>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ info.value }}</p>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import api from '../api/client'
import {
  BuildingIcon, SaveIcon, CheckCircleIcon, AlertCircleIcon, InfoIcon,
  ShieldCheckIcon, CodeIcon, IndianRupeeIcon, CalendarIcon,
  ImageIcon, CameraIcon, UploadIcon,
} from 'lucide-vue-next'

const auth    = useAuthStore()
const company = ref<any>(null)
const saving  = ref(false)
const msg     = ref('')
const msgType = ref<'success' | 'error'>('success')

const logoPreview  = ref<string | null>(null)
const logoUploading = ref(false)
const logoMsg      = ref('')
const logoOk       = ref(true)
const logoInput    = ref<HTMLInputElement>()

const appInfo = [
  { label: 'Version', value: 'v1.0.0', icon: CodeIcon },
  { label: 'License', value: 'AGPL v3', icon: ShieldCheckIcon },
  { label: 'GST Ready', value: 'India · All States', icon: IndianRupeeIcon },
  { label: 'Financial Year', value: '2025-26', icon: CalendarIcon },
]

async function onLogoSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  logoPreview.value = URL.createObjectURL(file)
  logoUploading.value = true
  logoMsg.value = ''
  try {
    const fd = new FormData()
    fd.append('logo', file)
    const { data } = await api.post(`/companies/${company.value.id}/logo`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    company.value.logo_url = data.logo_url
    logoPreview.value = null
    logoOk.value = true
    logoMsg.value = 'Logo uploaded!'
    setTimeout(() => logoMsg.value = '', 3000)
  } catch {
    logoOk.value = false
    logoMsg.value = 'Upload failed. Try a smaller image.'
  } finally {
    logoUploading.value = false
    if (logoInput.value) logoInput.value.value = ''
  }
}

function removeLogo() {
  logoPreview.value = null
  if (company.value) company.value.logo_url = null
}

async function save() {
  saving.value = true
  msg.value = ''
  try {
    const { data } = await api.put(`/companies/${company.value.id}`, company.value)
    company.value = data
    msg.value = 'Settings saved successfully!'
    msgType.value = 'success'
    setTimeout(() => msg.value = '', 3000)
  } catch (e: any) {
    msg.value = e.response?.data?.message ?? 'Error saving settings.'
    msgType.value = 'error'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  if (auth.user?.company_id) {
    const { data } = await api.get(`/companies/${auth.user.company_id}`)
    company.value = data
  }
})
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
