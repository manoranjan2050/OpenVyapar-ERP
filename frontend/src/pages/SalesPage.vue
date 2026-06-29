<template>
  <div class="space-y-5">

    <!-- Header -->
    <div class="page-header animate-fade-down">
      <div>
        <h1 class="page-title">Sales Invoices</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ invoices.length }} invoices · GST auto-calculated</p>
      </div>
      <div class="flex items-center gap-2">
        <button class="btn-secondary" @click="exportExcel" :disabled="!invoices.length">
          <DownloadIcon class="w-4 h-4" /> Export
        </button>
        <RouterLink to="/sales/new" class="btn-primary">
          <PlusIcon class="w-4 h-4" /> New Invoice
        </RouterLink>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 animate-fade-up">
      <div class="relative flex-1 max-w-xs">
        <SearchIcon class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
        <input v-model="searchQ" type="text" placeholder="Search invoice / customer…" class="input pl-10" @input="debouncedFetch" />
      </div>
      <div class="flex flex-wrap gap-2">
        <button v-for="s in statusOptions" :key="s.value"
          @click="statusFilter = s.value; loadInvoices()"
          class="px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all duration-200"
          :class="statusFilter === s.value
            ? 'bg-blue-600 text-white border-blue-600 shadow-lg shadow-blue-500/20'
            : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600'">
          {{ s.label }}
        </button>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 animate-fade-up delay-75">
      <div v-for="card in summaryCards" :key="card.label"
           class="card p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="card.bg">
          <component :is="card.icon" class="w-5 h-5" :class="card.text" />
        </div>
        <div>
          <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ card.label }}</p>
          <p class="text-lg font-extrabold" :class="card.text">{{ card.value }}</p>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden animate-fade-up delay-150">
      <table class="w-full">
        <thead>
          <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
            <th class="table-header">Invoice #</th>
            <th class="table-header">Customer</th>
            <th class="table-header">Date</th>
            <th class="table-header text-right">Amount</th>
            <th class="table-header text-right">Balance</th>
            <th class="table-header text-center">Status</th>
            <th class="table-header w-16"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">

          <tr v-if="loading" v-for="n in 5" :key="n">
            <td class="px-4 py-3.5" colspan="7">
              <div class="flex items-center gap-4">
                <div class="shimmer h-3 w-28 rounded-full"></div>
                <div class="shimmer h-3 w-32 rounded-full"></div>
                <div class="shimmer h-3 w-20 rounded-full ml-auto"></div>
              </div>
            </td>
          </tr>

          <tr v-else v-for="inv in invoices" :key="inv.id" class="table-row group cursor-pointer"
              @click="$router.push(`/sales/${inv.id}`)">
            <td class="table-cell">
              <span class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">{{ inv.invoice_number }}</span>
            </td>
            <td class="table-cell">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white text-xs font-bold">
                  {{ inv.customer?.name?.charAt(0) ?? 'C' }}
                </div>
                <span class="font-semibold text-gray-900 dark:text-white text-sm">{{ inv.customer?.name }}</span>
              </div>
            </td>
            <td class="table-cell text-gray-500 dark:text-gray-400 text-xs">{{ inv.invoice_date }}</td>
            <td class="table-cell text-right font-bold text-gray-900 dark:text-white">
              ₹{{ Number(inv.total_amount).toLocaleString('en-IN') }}
            </td>
            <td class="table-cell text-right font-bold"
                :class="Number(inv.balance_amount) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'">
              ₹{{ Number(inv.balance_amount).toLocaleString('en-IN') }}
            </td>
            <td class="table-cell text-center">
              <span :class="statusBadge(inv.status)" class="capitalize">{{ inv.status?.replace('_', ' ') }}</span>
            </td>
            <td class="table-cell text-right">
              <span class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 dark:text-blue-400
                           opacity-0 group-hover:opacity-100 transition-all">
                <EyeIcon class="w-3.5 h-3.5" /> View
              </span>
            </td>
          </tr>

          <tr v-if="!loading && !invoices.length">
            <td colspan="7" class="py-16 text-center">
              <div class="flex flex-col items-center gap-3">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                  <ReceiptIcon class="w-7 h-7 text-gray-400" />
                </div>
                <p class="text-sm font-semibold text-gray-500">No invoices yet</p>
                <RouterLink to="/sales/new" class="btn-primary mt-1">
                  <PlusIcon class="w-4 h-4" /> Create First Invoice
                </RouterLink>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import api from '../api/client'
import { PlusIcon, SearchIcon, ReceiptIcon, EyeIcon, CheckCircleIcon, ClockIcon, AlertCircleIcon, XCircleIcon, DownloadIcon } from 'lucide-vue-next'
import * as XLSX from 'xlsx'

const invoices = ref<any[]>([])
const statusFilter = ref('')
const searchQ = ref('')
const loading = ref(true)
const router = useRouter()

const statusOptions = [
  { label: 'All', value: '' },
  { label: 'Draft', value: 'draft' },
  { label: 'Confirmed', value: 'confirmed' },
  { label: 'Partly Paid', value: 'partially_paid' },
  { label: 'Paid', value: 'paid' },
  { label: 'Cancelled', value: 'cancelled' },
]

const summaryCards = computed(() => {
  const all = invoices.value
  const total = all.reduce((s, i) => s + Number(i.total_amount || 0), 0)
  const paid = all.filter(i => i.status === 'paid').length
  const pending = all.reduce((s, i) => s + Number(i.balance_amount || 0), 0)
  return [
    { label: 'Total Sales', value: '₹' + total.toLocaleString('en-IN', {maximumFractionDigits:0}), icon: ReceiptIcon, bg: 'bg-blue-50 dark:bg-blue-900/20', text: 'text-blue-600 dark:text-blue-400' },
    { label: 'Invoices', value: all.length, icon: ClockIcon, bg: 'bg-violet-50 dark:bg-violet-900/20', text: 'text-violet-600 dark:text-violet-400' },
    { label: 'Paid', value: paid, icon: CheckCircleIcon, bg: 'bg-emerald-50 dark:bg-emerald-900/20', text: 'text-emerald-600 dark:text-emerald-400' },
    { label: 'Outstanding', value: '₹' + pending.toLocaleString('en-IN', {maximumFractionDigits:0}), icon: AlertCircleIcon, bg: 'bg-orange-50 dark:bg-orange-900/20', text: 'text-orange-600 dark:text-orange-400' },
  ]
})

async function loadInvoices() {
  loading.value = true
  try {
    const { data } = await api.get('/sales-invoices', { params: { status: statusFilter.value || undefined, search: searchQ.value || undefined } })
    invoices.value = data.data
  } finally {
    loading.value = false
  }
}

const debouncedFetch = useDebounceFn(() => loadInvoices(), 300)

function exportExcel() {
  const rows = invoices.value.map(inv => ({
    'Invoice #': inv.invoice_number,
    Customer: inv.customer?.name ?? '',
    Date: inv.invoice_date,
    'Total ₹': Number(inv.total_amount),
    'Paid ₹': Number(inv.paid_amount),
    'Balance ₹': Number(inv.balance_amount),
    Status: inv.status,
  }))
  const ws = XLSX.utils.json_to_sheet(rows)
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Sales')
  XLSX.writeFile(wb, `sales_${new Date().toISOString().slice(0,10)}.xlsx`)
}

const statusBadge = (s: string) => ({
  draft: 'badge-gray', confirmed: 'badge-blue',
  partially_paid: 'badge-yellow', paid: 'badge-green', cancelled: 'badge-red',
}[s] ?? 'badge-gray')

onMounted(() => loadInvoices())
</script>
