<template>
  <div v-if="invoice" class="max-w-4xl animate-fade-up">

    <div class="flex items-center gap-3 mb-6 animate-fade-down">
      <RouterLink to="/purchases" class="btn-icon"><ArrowLeftIcon class="w-4 h-4" /></RouterLink>
      <div class="flex-1">
        <h1 class="page-title font-mono">{{ invoice.invoice_number }}</h1>
        <p class="text-sm text-gray-400 mt-0.5">{{ invoice.invoice_date }} · {{ invoice.supplier?.name }}</p>
      </div>
      <div class="flex items-center gap-2">
        <span :class="statusBadge(invoice.status)" class="capitalize text-sm">{{ invoice.status?.replace('_',' ') }}</span>
        <button class="btn-secondary" @click="window.print()">
          <PrinterIcon class="w-4 h-4" /> Print
        </button>
        <button v-if="invoice.status !== 'cancelled' && Number(invoice.balance_amount) > 0"
                class="btn-success" @click="showPayment = true">
          <IndianRupeeIcon class="w-4 h-4" /> Record Payment
        </button>
      </div>
    </div>

    <div id="printable-invoice" class="card p-8">

      <!-- Header -->
      <div class="flex items-start justify-between mb-8">
        <div>
          <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-600 to-violet-800 flex items-center justify-center">
              <span class="text-white font-extrabold text-sm">OV</span>
            </div>
            <div>
              <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">{{ auth.user?.company?.name }}</h2>
              <p class="text-xs text-gray-500">GSTIN: {{ auth.user?.company?.gstin ?? '–' }}</p>
            </div>
          </div>
          <p class="text-xs text-gray-500">{{ auth.user?.company?.address ?? '' }}</p>
        </div>
        <div class="text-right">
          <div class="inline-block px-4 py-2 rounded-xl bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-800 mb-2">
            <p class="text-xs text-violet-600 dark:text-violet-400 font-semibold uppercase tracking-wider">Purchase Bill</p>
            <p class="text-xl font-extrabold text-violet-700 dark:text-violet-300 font-mono">{{ invoice.invoice_number }}</p>
          </div>
          <div class="text-xs text-gray-500 space-y-0.5">
            <p>Date: <strong class="text-gray-700 dark:text-gray-300">{{ invoice.invoice_date }}</strong></p>
            <p v-if="invoice.supplier_invoice_number">Supplier Bill: <strong class="font-mono text-gray-700 dark:text-gray-300">{{ invoice.supplier_invoice_number }}</strong></p>
          </div>
        </div>
      </div>

      <!-- Supplier info -->
      <div class="grid grid-cols-2 gap-6 mb-8">
        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Supplier</p>
          <p class="font-bold text-gray-900 dark:text-white">{{ invoice.supplier?.name }}</p>
          <p v-if="invoice.supplier?.gstin" class="text-xs font-mono text-gray-500 mt-0.5">GSTIN: {{ invoice.supplier.gstin }}</p>
          <p v-if="invoice.supplier?.phone" class="text-xs text-gray-400 mt-1">{{ invoice.supplier.phone }}</p>
        </div>
        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Payment Status</p>
          <div class="space-y-1 text-xs">
            <div class="flex justify-between">
              <span class="text-gray-500">Total</span>
              <strong class="text-gray-900 dark:text-white">₹{{ fmt(invoice.total_amount) }}</strong>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Paid</span>
              <strong class="text-emerald-600">₹{{ fmt(invoice.paid_amount) }}</strong>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Payable</span>
              <strong :class="Number(invoice.balance_amount) > 0 ? 'text-rose-600' : 'text-emerald-600'">₹{{ fmt(invoice.balance_amount) }}</strong>
            </div>
          </div>
        </div>
      </div>

      <!-- Items -->
      <div class="mb-6">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-violet-900 dark:bg-violet-800 text-white rounded-xl overflow-hidden">
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider rounded-l-xl">#</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Product</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">HSN</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Qty</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Rate</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Taxable</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">GST%</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider rounded-r-xl">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in invoice.items" :key="item.id"
                class="border-b border-gray-100 dark:border-gray-800 hover:bg-violet-50/30 dark:hover:bg-violet-900/10 transition-colors">
              <td class="px-4 py-3 text-gray-400 text-xs">{{ i + 1 }}</td>
              <td class="px-4 py-3">
                <p class="font-semibold text-gray-900 dark:text-white">{{ item.product_name }}</p>
                <p v-if="item.unit" class="text-xs text-gray-400">{{ item.unit }}</p>
              </td>
              <td class="px-4 py-3 text-center font-mono text-xs text-gray-500">{{ item.hsn_code ?? '–' }}</td>
              <td class="px-4 py-3 text-right font-semibold">{{ item.quantity }}</td>
              <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">₹{{ fmt(item.rate) }}</td>
              <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">₹{{ fmt(item.taxable_amount) }}</td>
              <td class="px-4 py-3 text-right"><span class="badge-purple text-xs">{{ item.gst_rate }}%</span></td>
              <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">₹{{ fmt(item.total_amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Totals -->
      <div class="flex justify-end">
        <div class="w-60 space-y-1.5 text-sm">
          <div class="flex justify-between text-gray-500"><span>Subtotal</span><span>₹{{ fmt(invoice.subtotal) }}</span></div>
          <div class="flex justify-between text-gray-500"><span>Taxable</span><span>₹{{ fmt(invoice.taxable_amount) }}</span></div>
          <div v-if="Number(invoice.cgst_amount) > 0" class="flex justify-between text-gray-400"><span>CGST</span><span>₹{{ fmt(invoice.cgst_amount) }}</span></div>
          <div v-if="Number(invoice.sgst_amount) > 0" class="flex justify-between text-gray-400"><span>SGST</span><span>₹{{ fmt(invoice.sgst_amount) }}</span></div>
          <div v-if="Number(invoice.igst_amount) > 0" class="flex justify-between text-gray-400"><span>IGST</span><span>₹{{ fmt(invoice.igst_amount) }}</span></div>
          <div class="flex justify-between font-extrabold text-gray-900 dark:text-white border-t border-gray-200 dark:border-gray-700 pt-2 text-base">
            <span>Total</span><span>₹{{ fmt(invoice.total_amount) }}</span>
          </div>
          <div class="flex justify-between text-emerald-600 dark:text-emerald-400 font-semibold">
            <span>Paid</span><span>₹{{ fmt(invoice.paid_amount) }}</span>
          </div>
          <div class="flex justify-between font-bold rounded-xl p-2 -mx-2 text-base"
               :class="Number(invoice.balance_amount) > 0 ? 'bg-rose-50 dark:bg-rose-900/20 text-rose-600' : 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600'">
            <span>{{ Number(invoice.balance_amount) > 0 ? 'Amount Payable' : 'Fully Paid' }}</span>
            <span>₹{{ fmt(invoice.balance_amount) }}</span>
          </div>
        </div>
      </div>

      <div v-if="invoice.notes" class="mt-5 p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500">
        📝 {{ invoice.notes }}
      </div>

      <div class="mt-8 pt-5 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-400 text-center">
        Generated by <strong>OpenVyapar ERP</strong>
      </div>
    </div>
  </div>

  <div v-else class="flex flex-col items-center justify-center py-24 text-gray-400">
    <div class="w-10 h-10 border-2 border-violet-500 border-t-transparent rounded-full animate-spin mb-4"></div>
    <p class="text-sm">Loading purchase bill…</p>
  </div>

  <!-- Payment Modal -->
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="showPayment" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showPayment = false"></div>
        <div class="relative card w-full max-w-md p-6 shadow-2xl animate-scale-in">
          <div class="flex items-center justify-between mb-5">
            <div>
              <h3 class="text-lg font-bold text-gray-900 dark:text-white">Record Payment</h3>
              <p class="text-sm text-gray-400 mt-0.5">Payable: <strong class="text-rose-600">₹{{ fmt(invoice?.balance_amount) }}</strong></p>
            </div>
            <button @click="showPayment = false" class="btn-icon"><XIcon class="w-4 h-4" /></button>
          </div>
          <form @submit.prevent="recordPayment" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="label">Amount ₹ *</label>
                <input v-model="payForm.amount" type="number" step="0.01" min="0.01"
                       :max="invoice?.balance_amount" class="input text-lg font-bold" required />
              </div>
              <div>
                <label class="label">Payment Mode *</label>
                <select v-model="payForm.mode" class="input">
                  <option value="cash">💵 Cash</option>
                  <option value="upi">📱 UPI</option>
                  <option value="bank">🏦 Bank Transfer</option>
                  <option value="cheque">📄 Cheque</option>
                </select>
              </div>
              <div>
                <label class="label">Date *</label>
                <input v-model="payForm.payment_date" type="date" class="input" required />
              </div>
              <div class="col-span-2">
                <label class="label">Reference</label>
                <input v-model="payForm.reference" class="input" placeholder="UTR / cheque no." />
              </div>
            </div>
            <div class="flex gap-2">
              <button type="button" class="text-xs px-2 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 font-semibold"
                      @click="payForm.amount = invoice?.balance_amount">Full Amount</button>
            </div>
            <div v-if="payError" class="text-rose-600 text-sm bg-rose-50 dark:bg-rose-900/20 px-3 py-2 rounded-xl">{{ payError }}</div>
            <div class="flex gap-2">
              <button type="submit" class="btn-success flex-1 justify-center" :disabled="paying">
                {{ paying ? 'Saving…' : 'Confirm Payment' }}
              </button>
              <button type="button" class="btn-secondary" @click="showPayment = false">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import api from '../api/client'
import { useAuthStore } from '../stores/auth'
import { ArrowLeftIcon, PrinterIcon, IndianRupeeIcon, XIcon } from 'lucide-vue-next'

const route   = useRoute()
const auth    = useAuthStore()
const invoice = ref<any>(null)
const showPayment = ref(false)
const paying  = ref(false)
const payError = ref('')
const today   = new Date().toISOString().split('T')[0]
const payForm = ref({ amount: '', mode: 'cash', payment_date: today, reference: '' })
const window  = globalThis as any

const fmt = (n: any) => Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const statusBadge = (s: string) => ({ draft: 'badge-gray', confirmed: 'badge-blue', partially_paid: 'badge-yellow', paid: 'badge-green', cancelled: 'badge-red' }[s] ?? 'badge-gray')

async function recordPayment() {
  paying.value = true
  payError.value = ''
  try {
    const { data } = await api.post(`/purchase-invoices/${route.params.id}/payment`, payForm.value)
    invoice.value = data.invoice
    showPayment.value = false
    payForm.value = { amount: '', mode: 'cash', payment_date: today, reference: '' }
  } catch (e: any) {
    payError.value = e.response?.data?.message ?? 'Error.'
  } finally { paying.value = false }
}

onMounted(async () => {
  const { data } = await api.get(`/purchase-invoices/${route.params.id}`)
  invoice.value = data
})
</script>

<style>
@media print {
  body > * { display: none !important; }
  #app > * { display: none !important; }
  #printable-invoice { display: block !important; position: fixed !important; top:0; left:0; width:100%; background:white !important; color:black !important; }
}
</style>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
