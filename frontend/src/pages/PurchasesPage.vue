<template>
  <div class="space-y-5">

    <div class="page-header animate-fade-down">
      <div>
        <h1 class="page-title">Purchases</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ invoices.length }} purchase bills · GST auto-calculated</p>
      </div>
      <RouterLink to="/purchases/new" class="btn-primary">
        <PlusIcon class="w-4 h-4" /> New Purchase
      </RouterLink>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 animate-fade-up">
      <div class="flex flex-wrap gap-2">
        <button v-for="s in statusOptions" :key="s.value"
          @click="statusFilter = s.value; loadInvoices()"
          class="px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all duration-200"
          :class="statusFilter === s.value
            ? 'bg-violet-600 text-white border-violet-600 shadow-lg shadow-violet-500/20'
            : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-gray-300'">
          {{ s.label }}
        </button>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 animate-fade-up delay-75">
      <div v-for="card in summaryCards" :key="card.label" class="card p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="card.bg">
          <component :is="card.icon" class="w-5 h-5" :class="card.text" />
        </div>
        <div>
          <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ card.label }}</p>
          <p class="text-lg font-extrabold" :class="card.text">{{ card.value }}</p>
        </div>
      </div>
    </div>

    <div class="card overflow-hidden animate-fade-up delay-150">
      <table class="w-full">
        <thead>
          <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
            <th class="table-header">Bill #</th>
            <th class="table-header">Supplier</th>
            <th class="table-header">Date</th>
            <th class="table-header text-right">Amount</th>
            <th class="table-header text-right">Balance</th>
            <th class="table-header text-center">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">

          <tr v-if="loading" v-for="n in 5" :key="n">
            <td class="px-4 py-3.5" colspan="6">
              <div class="flex items-center gap-4">
                <div class="shimmer h-3 w-28 rounded-full"></div>
                <div class="shimmer h-3 w-32 rounded-full"></div>
                <div class="shimmer h-3 w-20 rounded-full ml-auto"></div>
              </div>
            </td>
          </tr>

          <tr v-else v-for="inv in invoices" :key="inv.id" class="table-row group cursor-pointer" @click="router.push(`/purchases/${inv.id}`)">
            <td class="table-cell">
              <span class="font-mono text-xs font-bold text-violet-600 dark:text-violet-400">{{ inv.invoice_number }}</span>
            </td>
            <td class="table-cell">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-violet-400 to-violet-600 flex items-center justify-center text-white text-xs font-bold">
                  {{ inv.supplier?.name?.charAt(0) ?? 'S' }}
                </div>
                <span class="font-semibold text-gray-900 dark:text-white text-sm">{{ inv.supplier?.name }}</span>
              </div>
            </td>
            <td class="table-cell text-gray-500 text-xs">{{ inv.invoice_date }}</td>
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
          </tr>

          <tr v-if="!loading && !invoices.length">
            <td colspan="6" class="py-16 text-center">
              <div class="flex flex-col items-center gap-3">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                  <ShoppingCartIcon class="w-7 h-7 text-gray-400" />
                </div>
                <p class="text-sm font-semibold text-gray-500">No purchase bills yet</p>
                <RouterLink to="/purchases/new" class="btn-primary mt-1">
                  <PlusIcon class="w-4 h-4" /> Record First Purchase
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
import api from '../api/client'
import { PlusIcon, ShoppingCartIcon, CheckCircleIcon, ClockIcon, AlertCircleIcon, IndianRupeeIcon } from 'lucide-vue-next'

const router = useRouter()
const invoices = ref<any[]>([])
const statusFilter = ref('')
const loading = ref(true)

const statusOptions = [
  { label: 'All', value: '' },
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
    { label: 'Total Purchases', value: '₹' + total.toLocaleString('en-IN', { maximumFractionDigits: 0 }), icon: IndianRupeeIcon, bg: 'bg-violet-50 dark:bg-violet-900/20', text: 'text-violet-600 dark:text-violet-400' },
    { label: 'Bills',           value: all.length, icon: ClockIcon,        bg: 'bg-blue-50 dark:bg-blue-900/20',   text: 'text-blue-600 dark:text-blue-400' },
    { label: 'Paid',            value: paid,        icon: CheckCircleIcon,  bg: 'bg-emerald-50 dark:bg-emerald-900/20', text: 'text-emerald-600 dark:text-emerald-400' },
    { label: 'Payable',         value: '₹' + pending.toLocaleString('en-IN', { maximumFractionDigits: 0 }), icon: AlertCircleIcon, bg: 'bg-orange-50 dark:bg-orange-900/20', text: 'text-orange-600 dark:text-orange-400' },
  ]
})

async function loadInvoices() {
  loading.value = true
  try {
    const { data } = await api.get('/purchase-invoices', { params: { status: statusFilter.value || undefined } })
    invoices.value = data.data
  } finally { loading.value = false }
}

const statusBadge = (s: string) => ({
  confirmed: 'badge-blue', partially_paid: 'badge-yellow', paid: 'badge-green', cancelled: 'badge-red',
}[s] ?? 'badge-gray')

onMounted(() => loadInvoices())
</script>
