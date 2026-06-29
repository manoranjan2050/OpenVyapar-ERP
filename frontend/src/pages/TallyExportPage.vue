<template>
  <div class="max-w-2xl space-y-6 animate-fade-up">

    <div class="page-header animate-fade-down">
      <div>
        <h1 class="page-title">Tally XML Export</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Export sales & purchases to Tally-compatible XML</p>
      </div>
    </div>

    <!-- How it works -->
    <div class="card p-5 border-l-4 border-blue-500 animate-fade-up">
      <p class="text-sm font-bold text-gray-800 dark:text-white mb-2">How to import in Tally</p>
      <ol class="text-sm text-gray-600 dark:text-gray-400 space-y-1.5 list-decimal list-inside">
        <li>Download the XML file below</li>
        <li>Open Tally ERP 9 or Tally Prime</li>
        <li>Go to <strong>Gateway of Tally → Import → Vouchers</strong></li>
        <li>Select the downloaded XML file</li>
        <li>Tally will import all sales and purchase vouchers automatically</li>
      </ol>
      <p class="text-xs text-gray-400 mt-3">⚠️ Make sure ledgers like CGST, SGST, IGST, and your customer/supplier names exist in Tally before importing.</p>
    </div>

    <!-- Export form -->
    <div class="card p-6 animate-fade-up delay-75">
      <h2 class="font-bold text-gray-900 dark:text-white mb-4">Select Period</h2>
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="label">From Date</label>
          <input v-model="from" type="date" class="input w-44" />
        </div>
        <div>
          <label class="label">To Date</label>
          <input v-model="to" type="date" class="input w-44" />
        </div>
        <div class="flex gap-2 flex-wrap">
          <button v-for="q in quarters" :key="q.label"
                  @click="from = q.from; to = q.to"
                  class="px-3 py-2 text-xs font-semibold rounded-xl border border-gray-200 dark:border-gray-700
                         hover:bg-blue-50 dark:hover:bg-blue-900/20 text-gray-600 dark:text-gray-400 transition-all">
            {{ q.label }}
          </button>
        </div>
      </div>

      <button class="btn-primary mt-5 w-full justify-center" @click="exportTally" :disabled="exporting">
        <svg v-if="exporting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
        <DownloadIcon v-else class="w-4 h-4" />
        {{ exporting ? 'Generating…' : 'Download Tally XML' }}
      </button>

      <div v-if="success" class="mt-3 flex items-center gap-2 text-emerald-600 text-sm bg-emerald-50 dark:bg-emerald-900/20 px-3 py-2 rounded-xl">
        <CheckCircleIcon class="w-4 h-4 flex-shrink-0" /> XML file downloaded successfully!
      </div>
    </div>

    <!-- GSTR-1 Excel export also here -->
    <div class="card p-6 animate-fade-up delay-150">
      <h2 class="font-bold text-gray-900 dark:text-white mb-1">Quick Export Links</h2>
      <p class="text-xs text-gray-400 mb-4">Go to the respective pages to export</p>
      <div class="grid grid-cols-2 gap-3">
        <RouterLink to="/sales" class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-700 transition-all group">
          <div class="w-9 h-9 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
            <ReceiptIcon class="w-4 h-4 text-blue-600 dark:text-blue-400" />
          </div>
          <div>
            <p class="text-sm font-semibold text-gray-800 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">Sales Excel</p>
            <p class="text-xs text-gray-400">All invoices → .xlsx</p>
          </div>
        </RouterLink>
        <RouterLink to="/products" class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-emerald-300 dark:hover:border-emerald-700 transition-all group">
          <div class="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
            <PackageIcon class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
          </div>
          <div>
            <p class="text-sm font-semibold text-gray-800 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400">Products Excel</p>
            <p class="text-xs text-gray-400">Product catalogue → .xlsx</p>
          </div>
        </RouterLink>
        <RouterLink to="/reports" class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-orange-300 dark:hover:border-orange-700 transition-all group">
          <div class="w-9 h-9 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
            <BarChart3Icon class="w-4 h-4 text-orange-600 dark:text-orange-400" />
          </div>
          <div>
            <p class="text-sm font-semibold text-gray-800 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400">GSTR-1 Excel</p>
            <p class="text-xs text-gray-400">B2B, B2C, HSN, ITC</p>
          </div>
        </RouterLink>
        <RouterLink to="/ledger" class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-violet-300 dark:hover:border-violet-700 transition-all group">
          <div class="w-9 h-9 rounded-xl bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
            <BookOpenIcon class="w-4 h-4 text-violet-600 dark:text-violet-400" />
          </div>
          <div>
            <p class="text-sm font-semibold text-gray-800 dark:text-white group-hover:text-violet-600 dark:group-hover:text-violet-400">Party Ledger</p>
            <p class="text-xs text-gray-400">Customer / Supplier</p>
          </div>
        </RouterLink>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../api/client'
import { DownloadIcon, CheckCircleIcon, ReceiptIcon, PackageIcon, BarChart3Icon, BookOpenIcon } from 'lucide-vue-next'

const fy       = new Date().getMonth() >= 3 ? new Date().getFullYear() : new Date().getFullYear() - 1
const from     = ref(`${fy}-04-01`)
const to       = ref(`${fy + 1}-03-31`)
const exporting = ref(false)
const success  = ref(false)

const quarters = [
  { label: 'Q1', from: `${fy}-04-01`, to: `${fy}-06-30` },
  { label: 'Q2', from: `${fy}-07-01`, to: `${fy}-09-30` },
  { label: 'Q3', from: `${fy}-10-01`, to: `${fy}-12-31` },
  { label: 'Q4', from: `${fy+1}-01-01`, to: `${fy+1}-03-31` },
  { label: `Full FY`, from: `${fy}-04-01`, to: `${fy+1}-03-31` },
]

async function exportTally() {
  exporting.value = true
  success.value   = false
  try {
    const resp = await api.get('/tally/export', {
      params: { from: from.value, to: to.value },
      responseType: 'blob',
    })
    const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/xml' }))
    const a    = document.createElement('a')
    a.href     = url
    a.download = `tally_${from.value}_to_${to.value}.xml`
    a.click()
    URL.revokeObjectURL(url)
    success.value = true
    setTimeout(() => { success.value = false }, 4000)
  } finally { exporting.value = false }
}
</script>
