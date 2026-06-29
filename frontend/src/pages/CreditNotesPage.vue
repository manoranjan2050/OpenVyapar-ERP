<template>
  <div class="max-w-5xl space-y-6 animate-fade-up">

    <div class="page-header animate-fade-down">
      <div>
        <h1 class="page-title">Credit Notes</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Issue credit notes for returns and cancellations</p>
      </div>
      <button class="btn-primary" @click="showForm = true">
        <PlusIcon class="w-4 h-4" /> New Credit Note
      </button>
    </div>

    <!-- List -->
    <div class="card overflow-hidden animate-fade-up">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
            <th class="table-header">Credit Note #</th>
            <th class="table-header">Customer</th>
            <th class="table-header">Date</th>
            <th class="table-header">Notes</th>
            <th class="table-header text-right">Amount</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
          <tr v-if="loading" v-for="n in 3" :key="n">
            <td colspan="5" class="px-4 py-3"><div class="shimmer h-3 w-full rounded-full"></div></td>
          </tr>
          <tr v-else v-for="cn in notes" :key="cn.id" class="table-row">
            <td class="table-cell font-mono text-xs font-bold text-orange-600 dark:text-orange-400">{{ cn.invoice_number }}</td>
            <td class="table-cell font-semibold text-gray-900 dark:text-white">{{ cn.customer?.name }}</td>
            <td class="table-cell text-xs text-gray-500">{{ cn.invoice_date }}</td>
            <td class="table-cell text-xs text-gray-400 max-w-xs truncate">{{ cn.notes }}</td>
            <td class="table-cell text-right font-bold text-orange-600 dark:text-orange-400">₹{{ fmt(cn.total_amount) }}</td>
          </tr>
          <tr v-if="!loading && !notes.length">
            <td colspan="5" class="py-14 text-center">
              <ReceiptIcon class="w-9 h-9 text-gray-300 dark:text-gray-600 mx-auto mb-2" />
              <p class="text-sm text-gray-400">No credit notes yet</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- New Credit Note Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showForm = false"></div>
          <div class="relative card w-full max-w-2xl p-6 shadow-2xl animate-scale-in max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-5">
              <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">New Credit Note</h3>
                <p class="text-xs text-gray-400 mt-0.5">Stock is reversed automatically</p>
              </div>
              <button @click="showForm = false" class="btn-icon"><XIcon class="w-4 h-4" /></button>
            </div>

            <form @submit.prevent="save" class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                  <label class="label">Original Invoice *</label>
                  <select v-model="form.original_invoice_id" class="input" required @change="loadInvoice">
                    <option value="">Select invoice to return…</option>
                    <option v-for="inv in invoices" :key="inv.id" :value="inv.id">
                      {{ inv.invoice_number }} — {{ inv.customer?.name }} (₹{{ fmt(inv.total_amount) }})
                    </option>
                  </select>
                </div>
                <div class="col-span-2">
                  <label class="label">Return Reason *</label>
                  <textarea v-model="form.reason" class="input resize-none" rows="2" required
                            placeholder="Customer returned damaged goods…" />
                </div>
              </div>

              <!-- Items to return -->
              <div v-if="selectedInvoice" class="space-y-3">
                <p class="text-sm font-bold text-gray-700 dark:text-gray-300">Items to Return</p>
                <div v-for="(item, i) in form.items" :key="i"
                     class="grid grid-cols-4 gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50">
                  <div class="col-span-2">
                    <p class="text-xs text-gray-400 font-medium mb-1">Product</p>
                    <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ item.product_name }}</p>
                  </div>
                  <div>
                    <label class="text-xs text-gray-400 font-medium block mb-1">Qty Return</label>
                    <input v-model="item.quantity" type="number" step="0.001" min="0"
                           :max="item.max_qty" class="input py-1.5 text-sm" />
                    <p class="text-xs text-gray-400 mt-0.5">Max: {{ item.max_qty }}</p>
                  </div>
                  <div>
                    <label class="text-xs text-gray-400 font-medium block mb-1">Rate</label>
                    <input v-model="item.rate" type="number" step="0.01" class="input py-1.5 text-sm" />
                  </div>
                </div>
              </div>

              <!-- Total preview -->
              <div v-if="cnTotal > 0" class="flex justify-between items-center p-3 rounded-xl bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800">
                <span class="text-sm font-semibold text-orange-700 dark:text-orange-400">Credit Note Total</span>
                <span class="text-lg font-extrabold text-orange-700 dark:text-orange-400">₹{{ fmt(cnTotal) }}</span>
              </div>

              <div v-if="error" class="flex items-center gap-2 text-rose-600 text-sm bg-rose-50 dark:bg-rose-900/20 px-3 py-2 rounded-xl">
                <AlertCircleIcon class="w-4 h-4 flex-shrink-0" /> {{ error }}
              </div>

              <div class="flex gap-2 pt-1">
                <button type="submit" class="btn-danger flex-1 justify-center" :disabled="saving || !cnTotal">
                  <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                  </svg>
                  {{ saving ? 'Issuing…' : 'Issue Credit Note' }}
                </button>
                <button type="button" class="btn-secondary" @click="showForm = false">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '../api/client'
import { PlusIcon, XIcon, AlertCircleIcon, ReceiptIcon } from 'lucide-vue-next'

const notes    = ref<any[]>([])
const invoices = ref<any[]>([])
const loading  = ref(true)
const showForm = ref(false)
const saving   = ref(false)
const error    = ref('')
const selectedInvoice = ref<any>(null)

const fmt = (n: any) => Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const form = ref<any>({ original_invoice_id: '', reason: '', items: [] })

const cnTotal = computed(() => {
  return form.value.items.reduce((sum: number, item: any) => {
    const q = Number(item.quantity) || 0
    const r = Number(item.rate) || 0
    const g = Number(item.gst_rate) || 0
    return sum + q * r * (1 + g / 100)
  }, 0)
})

async function loadInvoice() {
  if (!form.value.original_invoice_id) return
  const { data } = await api.get(`/sales-invoices/${form.value.original_invoice_id}`)
  selectedInvoice.value = data
  form.value.items = (data.items ?? []).map((item: any) => ({
    product_id:   item.product_id,
    product_name: item.product_name,
    quantity:     item.quantity,
    max_qty:      item.quantity,
    rate:         item.rate,
    gst_rate:     item.gst_rate,
  }))
}

async function save() {
  saving.value = true
  error.value  = ''
  try {
    const payload = {
      original_invoice_id: form.value.original_invoice_id,
      reason:              form.value.reason,
      items: form.value.items
        .filter((i: any) => Number(i.quantity) > 0)
        .map((i: any) => ({ product_id: i.product_id, quantity: i.quantity, rate: i.rate, gst_rate: i.gst_rate })),
    }
    if (!payload.items.length) { error.value = 'Select at least one item with quantity > 0.'; saving.value = false; return }
    await api.post('/credit-notes', payload)
    showForm.value    = false
    selectedInvoice.value = null
    form.value        = { original_invoice_id: '', reason: '', items: [] }
    await loadNotes()
  } catch (e: any) {
    error.value = e.response?.data?.message ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ') ?? 'Error issuing credit note.'
  } finally { saving.value = false }
}

async function loadNotes() {
  loading.value = true
  const [cn, inv] = await Promise.all([
    api.get('/credit-notes'),
    api.get('/sales-invoices', { params: { status: 'confirmed,partially_paid,paid', per_page: 200 } }),
  ])
  notes.value    = cn.data.data ?? cn.data
  invoices.value = inv.data.data ?? inv.data
  loading.value  = false
}

onMounted(() => loadNotes())
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
