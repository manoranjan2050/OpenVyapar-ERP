<template>
  <div class="space-y-6 max-w-6xl animate-fade-up">

    <!-- Header -->
    <div class="page-header animate-fade-down">
      <div>
        <h1 class="page-title">GST Reports</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">GSTR-1 · ITC · Tax Liability</p>
      </div>
    </div>

    <!-- Period selector -->
    <div class="card p-5 flex flex-wrap items-end gap-4 animate-fade-up">
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
                @click="setQuarter(q)"
                class="px-3 py-2 text-xs font-semibold rounded-xl border border-gray-200 dark:border-gray-700
                       hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:border-blue-300 dark:hover:border-blue-700
                       text-gray-600 dark:text-gray-400 transition-all">
          {{ q.label }}
        </button>
      </div>
      <button class="btn-primary" @click="loadAll" :disabled="loading">
        <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
        <BarChart3Icon v-else class="w-4 h-4" />
        {{ loading ? 'Loading…' : 'Generate Report' }}
      </button>
    </div>

    <div v-if="gstr1" class="space-y-6">

      <!-- Tax Liability Summary -->
      <div v-if="liability" class="grid grid-cols-1 lg:grid-cols-3 gap-4 animate-fade-up">
        <div class="card p-5 bg-gradient-to-br from-blue-600 to-blue-800 text-white">
          <p class="text-xs font-bold uppercase tracking-wider opacity-75 mb-1">Output Tax (Sales)</p>
          <p class="text-2xl font-extrabold">₹{{ fmt(liability.output_tax.cgst + liability.output_tax.sgst + liability.output_tax.igst) }}</p>
          <div class="mt-3 space-y-1 text-xs opacity-80">
            <div class="flex justify-between"><span>CGST</span><span>₹{{ fmt(liability.output_tax.cgst) }}</span></div>
            <div class="flex justify-between"><span>SGST</span><span>₹{{ fmt(liability.output_tax.sgst) }}</span></div>
            <div class="flex justify-between"><span>IGST</span><span>₹{{ fmt(liability.output_tax.igst) }}</span></div>
          </div>
        </div>
        <div class="card p-5 bg-gradient-to-br from-emerald-600 to-emerald-800 text-white">
          <p class="text-xs font-bold uppercase tracking-wider opacity-75 mb-1">Input Tax Credit (Purchases)</p>
          <p class="text-2xl font-extrabold">₹{{ fmt(liability.input_tax.cgst + liability.input_tax.sgst + liability.input_tax.igst) }}</p>
          <div class="mt-3 space-y-1 text-xs opacity-80">
            <div class="flex justify-between"><span>CGST</span><span>₹{{ fmt(liability.input_tax.cgst) }}</span></div>
            <div class="flex justify-between"><span>SGST</span><span>₹{{ fmt(liability.input_tax.sgst) }}</span></div>
            <div class="flex justify-between"><span>IGST</span><span>₹{{ fmt(liability.input_tax.igst) }}</span></div>
          </div>
        </div>
        <div class="card p-5 bg-gradient-to-br from-orange-600 to-rose-700 text-white">
          <p class="text-xs font-bold uppercase tracking-wider opacity-75 mb-1">Net Tax Payable</p>
          <p class="text-2xl font-extrabold">₹{{ fmt(liability.net_payable.total) }}</p>
          <div class="mt-3 space-y-1 text-xs opacity-80">
            <div class="flex justify-between"><span>CGST Payable</span><span>₹{{ fmt(liability.net_payable.cgst) }}</span></div>
            <div class="flex justify-between"><span>SGST Payable</span><span>₹{{ fmt(liability.net_payable.sgst) }}</span></div>
            <div class="flex justify-between"><span>IGST Payable</span><span>₹{{ fmt(liability.net_payable.igst) }}</span></div>
          </div>
        </div>
      </div>

      <!-- GSTR-1 Summary bar -->
      <div class="card p-5 animate-fade-up">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="font-bold text-gray-900 dark:text-white">GSTR-1 Summary</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ gstr1.summary.period_from }} to {{ gstr1.summary.period_to }}</p>
          </div>
          <div class="flex gap-2">
            <button class="btn-secondary text-xs" @click="exportGSTR1">
              <DownloadIcon class="w-3.5 h-3.5" /> Export Excel
            </button>
          </div>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div v-for="s in summaryCards" :key="s.label" class="rounded-xl p-3 text-center" :class="s.bg">
            <p class="text-2xl font-extrabold" :class="s.text">{{ s.value }}</p>
            <p class="text-xs mt-1" :class="s.sub">{{ s.label }}</p>
          </div>
        </div>
      </div>

      <!-- Tabs: B2B / B2C / HSN / ITC -->
      <div class="card overflow-hidden animate-fade-up delay-75">
        <div class="flex border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/40">
          <button v-for="t in tabs" :key="t"
                  @click="activeTab = t"
                  class="px-5 py-3 text-sm font-semibold transition-all border-b-2"
                  :class="activeTab === t
                    ? 'border-blue-600 text-blue-600 dark:text-blue-400 bg-white dark:bg-gray-900'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'">
            {{ t }}
          </button>
        </div>

        <!-- B2B -->
        <div v-if="activeTab === 'B2B'" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30">
                <th class="table-header">Invoice #</th>
                <th class="table-header">Customer</th>
                <th class="table-header font-mono">GSTIN</th>
                <th class="table-header text-center">Date</th>
                <th class="table-header text-right">Taxable</th>
                <th class="table-header text-right">CGST</th>
                <th class="table-header text-right">SGST</th>
                <th class="table-header text-right">IGST</th>
                <th class="table-header text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
              <tr v-for="r in gstr1.b2b" :key="r.invoice_number" class="table-row">
                <td class="table-cell font-mono text-xs text-blue-600 dark:text-blue-400 font-bold">{{ r.invoice_number }}</td>
                <td class="table-cell font-semibold text-gray-900 dark:text-white">{{ r.customer }}</td>
                <td class="table-cell font-mono text-xs text-gray-500">{{ r.gstin }}</td>
                <td class="table-cell text-center text-xs text-gray-500">{{ r.invoice_date }}</td>
                <td class="table-cell text-right">₹{{ fmt(r.taxable_amount) }}</td>
                <td class="table-cell text-right text-gray-500">₹{{ fmt(r.cgst) }}</td>
                <td class="table-cell text-right text-gray-500">₹{{ fmt(r.sgst) }}</td>
                <td class="table-cell text-right text-gray-500">₹{{ fmt(r.igst) }}</td>
                <td class="table-cell text-right font-bold text-gray-900 dark:text-white">₹{{ fmt(r.total) }}</td>
              </tr>
              <tr v-if="!gstr1.b2b.length">
                <td colspan="9" class="py-10 text-center text-sm text-gray-400">No B2B invoices in this period</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- B2C -->
        <div v-if="activeTab === 'B2C'" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30">
                <th class="table-header">Supply Type</th>
                <th class="table-header text-right">Invoices</th>
                <th class="table-header text-right">Taxable</th>
                <th class="table-header text-right">CGST</th>
                <th class="table-header text-right">SGST</th>
                <th class="table-header text-right">IGST</th>
                <th class="table-header text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
              <tr v-for="r in gstr1.b2c" :key="r.supply_type" class="table-row">
                <td class="table-cell"><span class="badge-blue capitalize">{{ r.supply_type ?? 'intra' }}-state</span></td>
                <td class="table-cell text-right font-semibold">{{ r.invoice_count }}</td>
                <td class="table-cell text-right">₹{{ fmt(r.taxable_amount) }}</td>
                <td class="table-cell text-right text-gray-500">₹{{ fmt(r.cgst) }}</td>
                <td class="table-cell text-right text-gray-500">₹{{ fmt(r.sgst) }}</td>
                <td class="table-cell text-right text-gray-500">₹{{ fmt(r.igst) }}</td>
                <td class="table-cell text-right font-bold">₹{{ fmt(r.total) }}</td>
              </tr>
              <tr v-if="!gstr1.b2c.length">
                <td colspan="7" class="py-10 text-center text-sm text-gray-400">No B2C invoices in this period</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- HSN -->
        <div v-if="activeTab === 'HSN Summary'" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30">
                <th class="table-header">HSN Code</th>
                <th class="table-header">Description</th>
                <th class="table-header text-center">UQC</th>
                <th class="table-header text-right">Qty</th>
                <th class="table-header text-right">Taxable</th>
                <th class="table-header text-right">CGST</th>
                <th class="table-header text-right">SGST</th>
                <th class="table-header text-right">IGST</th>
                <th class="table-header text-right">Total Tax</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
              <tr v-for="r in gstr1.hsn" :key="r.hsn_code" class="table-row">
                <td class="table-cell font-mono text-xs font-bold text-violet-600 dark:text-violet-400">{{ r.hsn_code }}</td>
                <td class="table-cell text-gray-700 dark:text-gray-300">{{ r.description }}</td>
                <td class="table-cell text-center text-xs text-gray-500">{{ r.uqc }}</td>
                <td class="table-cell text-right font-semibold">{{ r.quantity }}</td>
                <td class="table-cell text-right">₹{{ fmt(r.taxable_amount) }}</td>
                <td class="table-cell text-right text-gray-500">₹{{ fmt(r.cgst) }}</td>
                <td class="table-cell text-right text-gray-500">₹{{ fmt(r.sgst) }}</td>
                <td class="table-cell text-right text-gray-500">₹{{ fmt(r.igst) }}</td>
                <td class="table-cell text-right font-bold text-orange-600 dark:text-orange-400">₹{{ fmt(r.total_tax) }}</td>
              </tr>
              <tr v-if="!gstr1.hsn.length">
                <td colspan="9" class="py-10 text-center text-sm text-gray-400">No HSN data in this period</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ITC -->
        <div v-if="activeTab === 'ITC (Purchases)'" class="overflow-x-auto">
          <div v-if="itc" class="p-4 border-b border-gray-100 dark:border-gray-800 bg-emerald-50 dark:bg-emerald-900/10">
            <div class="flex flex-wrap gap-6 text-sm">
              <div><span class="text-gray-500">Total Bills:</span> <strong>{{ itc.summary.total_invoices }}</strong></div>
              <div><span class="text-gray-500">Taxable:</span> <strong>₹{{ fmt(itc.summary.taxable_amount) }}</strong></div>
              <div><span class="text-gray-500">Total ITC:</span> <strong class="text-emerald-600">₹{{ fmt(itc.summary.total_itc) }}</strong></div>
            </div>
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30">
                <th class="table-header">Bill #</th>
                <th class="table-header">Supplier Bill</th>
                <th class="table-header">Supplier</th>
                <th class="table-header font-mono">GSTIN</th>
                <th class="table-header text-center">Date</th>
                <th class="table-header text-right">Taxable</th>
                <th class="table-header text-right">CGST</th>
                <th class="table-header text-right">SGST</th>
                <th class="table-header text-right">IGST</th>
                <th class="table-header text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
              <tr v-for="r in itc?.rows" :key="r.invoice_number" class="table-row">
                <td class="table-cell font-mono text-xs text-violet-600 dark:text-violet-400 font-bold">{{ r.invoice_number }}</td>
                <td class="table-cell font-mono text-xs text-gray-500">{{ r.supplier_invoice ?? '–' }}</td>
                <td class="table-cell font-semibold text-gray-900 dark:text-white">{{ r.supplier }}</td>
                <td class="table-cell font-mono text-xs text-gray-400">{{ r.gstin ?? '–' }}</td>
                <td class="table-cell text-center text-xs text-gray-500">{{ r.invoice_date }}</td>
                <td class="table-cell text-right">₹{{ fmt(r.taxable_amount) }}</td>
                <td class="table-cell text-right text-gray-500">₹{{ fmt(r.cgst) }}</td>
                <td class="table-cell text-right text-gray-500">₹{{ fmt(r.sgst) }}</td>
                <td class="table-cell text-right text-gray-500">₹{{ fmt(r.igst) }}</td>
                <td class="table-cell text-right font-bold text-emerald-600 dark:text-emerald-400">₹{{ fmt(r.total) }}</td>
              </tr>
              <tr v-if="!itc?.rows?.length">
                <td colspan="10" class="py-10 text-center text-sm text-gray-400">No purchase bills in this period</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="!gstr1 && !loading" class="card py-20 text-center animate-fade-up">
      <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-4">
        <BarChart3Icon class="w-8 h-8 text-gray-400" />
      </div>
      <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">Select a period and click Generate Report</p>
      <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">GSTR-1, ITC and Tax Liability will appear here</p>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '../api/client'
import { BarChart3Icon, DownloadIcon } from 'lucide-vue-next'
import * as XLSX from 'xlsx'

const gstr1 = ref<any>(null)
const itc   = ref<any>(null)
const liability = ref<any>(null)
const loading = ref(false)
const activeTab = ref('B2B')
const tabs = ['B2B', 'B2C', 'HSN Summary', 'ITC (Purchases)']

// Default to current financial year
const fy = new Date().getMonth() >= 3 ? new Date().getFullYear() : new Date().getFullYear() - 1
const from = ref(`${fy}-04-01`)
const to   = ref(`${fy + 1}-03-31`)

const quarters = [
  { label: 'Q1 (Apr–Jun)', from: `${fy}-04-01`, to: `${fy}-06-30` },
  { label: 'Q2 (Jul–Sep)', from: `${fy}-07-01`, to: `${fy}-09-30` },
  { label: 'Q3 (Oct–Dec)', from: `${fy}-10-01`, to: `${fy}-12-31` },
  { label: 'Q4 (Jan–Mar)', from: `${fy+1}-01-01`, to: `${fy+1}-03-31` },
  { label: `FY ${fy}-${(fy+1).toString().slice(2)}`, from: `${fy}-04-01`, to: `${fy+1}-03-31` },
]

function setQuarter(q: any) { from.value = q.from; to.value = q.to }

const fmt = (n: number) => Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const summaryCards = computed(() => {
  const s = gstr1.value?.summary ?? {}
  return [
    { label: 'Total Invoices',   value: s.total_invoices ?? 0,              bg: 'bg-blue-50 dark:bg-blue-900/20',    text: 'text-blue-700 dark:text-blue-300',    sub: 'text-blue-500 dark:text-blue-400' },
    { label: 'Taxable Amount',   value: '₹' + fmt(s.taxable_amount ?? 0),   bg: 'bg-gray-50 dark:bg-gray-800/50',    text: 'text-gray-800 dark:text-gray-200',    sub: 'text-gray-400' },
    { label: 'Total Tax',        value: '₹' + fmt(s.total_tax ?? 0),        bg: 'bg-orange-50 dark:bg-orange-900/20',text: 'text-orange-700 dark:text-orange-300', sub: 'text-orange-500 dark:text-orange-400' },
    { label: 'Total Invoice Value', value: '₹' + fmt(s.total_amount ?? 0),  bg: 'bg-emerald-50 dark:bg-emerald-900/20', text: 'text-emerald-700 dark:text-emerald-300', sub: 'text-emerald-500 dark:text-emerald-400' },
  ]
})

async function loadAll() {
  loading.value = true
  try {
    const params = { from: from.value, to: to.value }
    const [r1, r2, r3] = await Promise.all([
      api.get('/reports/gstr1', { params }),
      api.get('/reports/itc',   { params }),
      api.get('/reports/tax-liability', { params }),
    ])
    gstr1.value     = r1.data
    itc.value       = r2.data
    liability.value = r3.data
  } finally {
    loading.value = false
  }
}

function exportGSTR1() {
  const wb = XLSX.utils.book_new()
  const b2bWs = XLSX.utils.json_to_sheet(gstr1.value?.b2b ?? [])
  const b2cWs = XLSX.utils.json_to_sheet(gstr1.value?.b2c ?? [])
  const hsnWs = XLSX.utils.json_to_sheet(gstr1.value?.hsn ?? [])
  const itcWs = XLSX.utils.json_to_sheet(itc.value?.rows ?? [])
  XLSX.utils.book_append_sheet(wb, b2bWs, 'B2B')
  XLSX.utils.book_append_sheet(wb, b2cWs, 'B2C')
  XLSX.utils.book_append_sheet(wb, hsnWs, 'HSN Summary')
  XLSX.utils.book_append_sheet(wb, itcWs, 'ITC')
  XLSX.writeFile(wb, `GSTR1_${from.value}_to_${to.value}.xlsx`)
}
</script>
