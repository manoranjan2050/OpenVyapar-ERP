<template>
  <div v-if="invoice" class="max-w-4xl animate-fade-up">

    <!-- Top bar -->
    <div class="flex items-center gap-3 mb-6 animate-fade-down">
      <RouterLink to="/sales" class="btn-icon"><ArrowLeftIcon class="w-4 h-4" /></RouterLink>
      <div class="flex-1">
        <h1 class="page-title font-mono">{{ invoice.invoice_number }}</h1>
        <p class="text-sm text-gray-400 mt-0.5">{{ invoice.invoice_date }} · {{ invoice.customer?.name }}</p>
      </div>
      <div class="flex items-center gap-2">
        <span :class="statusBadge(invoice.status)" class="capitalize text-sm">
          {{ invoice.status?.replace('_', ' ') }}
        </span>
        <button class="btn-secondary" @click="whatsapp" title="Share on WhatsApp">
          <MessageCircleIcon class="w-4 h-4 text-emerald-500" /> WhatsApp
        </button>
        <button class="btn-secondary" @click="printInvoice">
          <PrinterIcon class="w-4 h-4" /> Print
        </button>
        <button v-if="invoice.status !== 'cancelled' && Number(invoice.balance_amount) > 0"
                class="btn-success" @click="showPayment = true">
          <IndianRupeeIcon class="w-4 h-4" /> Record Payment
        </button>
        <button v-if="invoice.status !== 'cancelled'" class="btn-danger" @click="cancel">
          <XCircleIcon class="w-4 h-4" /> Cancel
        </button>
      </div>
    </div>

    <!-- Invoice card (also printed) -->
    <div id="printable-invoice" class="card p-8 print:shadow-none print:border-none">

      <!-- Company + Customer header -->
      <div class="flex items-start justify-between mb-8">
        <div>
          <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center print:bg-blue-700">
              <span class="text-white font-extrabold text-sm">OV</span>
            </div>
            <div>
              <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">{{ invoice.company?.name ?? auth.user?.company?.name }}</h2>
              <p class="text-xs text-gray-500">GSTIN: {{ invoice.company?.gstin ?? auth.user?.company?.gstin ?? '–' }}</p>
            </div>
          </div>
          <p class="text-xs text-gray-500 max-w-xs">{{ invoice.company?.address ?? auth.user?.company?.address ?? '' }}</p>
        </div>
        <div class="text-right">
          <div class="inline-block px-4 py-2 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 mb-2">
            <p class="text-xs text-blue-600 dark:text-blue-400 font-semibold uppercase tracking-wider">Tax Invoice</p>
            <p class="text-xl font-extrabold text-blue-700 dark:text-blue-300 font-mono">{{ invoice.invoice_number }}</p>
          </div>
          <div class="text-xs text-gray-500 space-y-0.5">
            <p>Date: <strong class="text-gray-700 dark:text-gray-300">{{ invoice.invoice_date }}</strong></p>
            <p v-if="invoice.due_date">Due: <strong class="text-gray-700 dark:text-gray-300">{{ invoice.due_date }}</strong></p>
          </div>
        </div>
      </div>

      <!-- Bill To -->
      <div class="grid grid-cols-2 gap-6 mb-8">
        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Bill To</p>
          <p class="font-bold text-gray-900 dark:text-white">{{ invoice.customer?.name }}</p>
          <p v-if="invoice.customer?.gstin" class="text-xs font-mono text-gray-500 mt-0.5">GSTIN: {{ invoice.customer.gstin }}</p>
          <p v-if="invoice.billing_address" class="text-xs text-gray-500 mt-1 leading-relaxed">{{ invoice.billing_address }}</p>
          <p v-if="invoice.customer?.phone" class="text-xs text-gray-500 mt-1">📞 {{ invoice.customer.phone }}</p>
        </div>
        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Payment Info</p>
          <div class="space-y-1 text-xs">
            <div class="flex justify-between">
              <span class="text-gray-500">Mode</span>
              <span class="font-semibold text-gray-700 dark:text-gray-300 capitalize">{{ invoice.payment_mode ?? 'Cash' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Supply Type</span>
              <span class="font-semibold text-gray-700 dark:text-gray-300 capitalize">{{ invoice.supply_type ?? 'Intra' }}-state</span>
            </div>
            <div v-if="invoice.upi_ref" class="flex justify-between">
              <span class="text-gray-500">UPI Ref</span>
              <span class="font-mono text-gray-700 dark:text-gray-300">{{ invoice.upi_ref }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Items table -->
      <div class="mb-6">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-900 dark:bg-gray-700 text-white rounded-xl overflow-hidden">
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider rounded-l-xl">#</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Product</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">HSN</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Qty</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Rate</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Disc%</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Taxable</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">GST%</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider rounded-r-xl">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in invoice.items" :key="item.id"
                class="border-b border-gray-100 dark:border-gray-800 hover:bg-blue-50/30 dark:hover:bg-blue-900/10 transition-colors">
              <td class="px-4 py-3 text-gray-400 text-xs">{{ i + 1 }}</td>
              <td class="px-4 py-3">
                <p class="font-semibold text-gray-900 dark:text-white">{{ item.product_name }}</p>
                <p v-if="item.unit" class="text-xs text-gray-400">{{ item.unit }}</p>
              </td>
              <td class="px-4 py-3 text-center font-mono text-xs text-gray-500">{{ item.hsn_code ?? '–' }}</td>
              <td class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">{{ item.quantity }}</td>
              <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">₹{{ Number(item.rate).toLocaleString('en-IN') }}</td>
              <td class="px-4 py-3 text-right text-gray-500">{{ item.discount_pct ? item.discount_pct + '%' : '–' }}</td>
              <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">₹{{ Number(item.taxable_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</td>
              <td class="px-4 py-3 text-right">
                <span class="badge-blue text-xs">{{ item.gst_rate }}%</span>
              </td>
              <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">₹{{ Number(item.total_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Totals -->
      <div class="flex flex-col lg:flex-row gap-6 justify-between">
        <!-- GST breakup -->
        <div class="flex-1">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Tax Summary</p>
          <table class="text-xs w-full max-w-xs">
            <thead>
              <tr class="text-gray-400">
                <th class="text-left py-1 font-semibold">Tax Type</th>
                <th class="text-right py-1 font-semibold">Base</th>
                <th class="text-right py-1 font-semibold">Amount</th>
              </tr>
            </thead>
            <tbody class="text-gray-600 dark:text-gray-400">
              <tr v-if="Number(invoice.cgst_amount) > 0">
                <td class="py-0.5">CGST</td>
                <td class="text-right">₹{{ Number(invoice.taxable_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</td>
                <td class="text-right font-semibold">₹{{ Number(invoice.cgst_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</td>
              </tr>
              <tr v-if="Number(invoice.sgst_amount) > 0">
                <td class="py-0.5">SGST</td>
                <td class="text-right">₹{{ Number(invoice.taxable_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</td>
                <td class="text-right font-semibold">₹{{ Number(invoice.sgst_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</td>
              </tr>
              <tr v-if="Number(invoice.igst_amount) > 0">
                <td class="py-0.5">IGST</td>
                <td class="text-right">₹{{ Number(invoice.taxable_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</td>
                <td class="text-right font-semibold">₹{{ Number(invoice.igst_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</td>
              </tr>
            </tbody>
          </table>

          <p v-if="invoice.notes" class="mt-3 text-xs text-gray-500 italic">
            📝 {{ invoice.notes }}
          </p>
        </div>

        <!-- Amount summary -->
        <div class="w-64 space-y-1.5 text-sm">
          <div class="flex justify-between text-gray-600 dark:text-gray-400">
            <span>Subtotal</span>
            <span>₹{{ Number(invoice.subtotal).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</span>
          </div>
          <div v-if="Number(invoice.discount_amount) > 0" class="flex justify-between text-rose-600">
            <span>Discount</span>
            <span>–₹{{ Number(invoice.discount_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</span>
          </div>
          <div class="flex justify-between text-gray-600 dark:text-gray-400">
            <span>Taxable Amount</span>
            <span>₹{{ Number(invoice.taxable_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</span>
          </div>
          <div v-if="Number(invoice.cgst_amount) > 0" class="flex justify-between text-gray-500">
            <span>CGST</span><span>₹{{ Number(invoice.cgst_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</span>
          </div>
          <div v-if="Number(invoice.sgst_amount) > 0" class="flex justify-between text-gray-500">
            <span>SGST</span><span>₹{{ Number(invoice.sgst_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</span>
          </div>
          <div v-if="Number(invoice.igst_amount) > 0" class="flex justify-between text-gray-500">
            <span>IGST</span><span>₹{{ Number(invoice.igst_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</span>
          </div>
          <div v-if="Number(invoice.round_off) !== 0" class="flex justify-between text-gray-500">
            <span>Round Off</span><span>₹{{ Number(invoice.round_off).toFixed(2) }}</span>
          </div>
          <div class="flex justify-between font-extrabold text-gray-900 dark:text-white border-t border-gray-200 dark:border-gray-700 pt-2 mt-1 text-base">
            <span>Total</span>
            <span>₹{{ Number(invoice.total_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</span>
          </div>
          <div class="flex justify-between text-emerald-600 dark:text-emerald-400 font-semibold">
            <span>Paid</span>
            <span>₹{{ Number(invoice.paid_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</span>
          </div>
          <div class="flex justify-between font-bold text-base rounded-xl p-2 -mx-2"
               :class="Number(invoice.balance_amount) > 0 ? 'bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400' : 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400'">
            <span>{{ Number(invoice.balance_amount) > 0 ? 'Balance Due' : 'Paid in Full' }}</span>
            <span>₹{{ Number(invoice.balance_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) }}</span>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="mt-8 pt-5 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs text-gray-400">
        <span>Generated by <strong>OpenVyapar ERP</strong> · AGPL v3</span>
        <span>This is a computer-generated invoice and does not require a physical signature.</span>
      </div>
    </div>

  </div>

  <div v-else class="flex flex-col items-center justify-center py-24 text-gray-400">
    <div class="w-10 h-10 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mb-4"></div>
    <p class="text-sm font-medium">Loading invoice…</p>
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
              <p class="text-sm text-gray-400 mt-0.5">
                Balance due: <strong class="text-rose-600">₹{{ Number(invoice?.balance_amount).toLocaleString('en-IN') }}</strong>
              </p>
            </div>
            <button @click="showPayment = false" class="btn-icon"><XIcon class="w-4 h-4" /></button>
          </div>

          <form @submit.prevent="recordPayment" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="label">Amount ₹ *</label>
                <input v-model="payForm.amount" type="number" step="0.01" min="0.01"
                       :max="invoice?.balance_amount" class="input text-lg font-bold" required
                       placeholder="0.00" />
              </div>
              <div>
                <label class="label">Payment Mode *</label>
                <select v-model="payForm.mode" class="input">
                  <option value="cash">💵 Cash</option>
                  <option value="upi">📱 UPI</option>
                  <option value="bank">🏦 Bank Transfer</option>
                  <option value="cheque">📄 Cheque</option>
                  <option value="card">💳 Card</option>
                </select>
              </div>
              <div>
                <label class="label">Payment Date *</label>
                <input v-model="payForm.payment_date" type="date" class="input" required />
              </div>
              <div class="col-span-2">
                <label class="label">Reference / UTR</label>
                <input v-model="payForm.reference" class="input" placeholder="Transaction ID / cheque no." />
              </div>
              <div class="col-span-2">
                <label class="label">Notes</label>
                <textarea v-model="payForm.notes" class="input resize-none" rows="2" placeholder="Optional note…" />
              </div>
            </div>

            <div v-if="payError" class="flex items-center gap-2 text-rose-600 text-sm bg-rose-50 dark:bg-rose-900/20 px-3 py-2 rounded-xl">
              <AlertCircleIcon class="w-4 h-4 flex-shrink-0" />{{ payError }}
            </div>

            <!-- Quick amount buttons -->
            <div class="flex gap-2">
              <button type="button" class="text-xs px-2 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 font-semibold hover:bg-blue-100 transition-colors"
                      @click="payForm.amount = invoice?.balance_amount">Full Amount</button>
              <button type="button" class="text-xs px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-semibold hover:bg-gray-200 transition-colors"
                      @click="payForm.amount = (Number(invoice?.balance_amount) / 2).toFixed(2)">Half</button>
            </div>

            <div class="flex gap-2 pt-1">
              <button type="submit" class="btn-success flex-1 justify-center" :disabled="paying">
                <svg v-if="paying" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <CheckIcon v-else class="w-4 h-4" />
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
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '../api/client'
import { useAuthStore } from '../stores/auth'
import {
  ArrowLeftIcon, PrinterIcon, IndianRupeeIcon, XCircleIcon,
  XIcon, AlertCircleIcon, CheckIcon, MessageCircleIcon,
} from 'lucide-vue-next'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()
const invoice = ref<any>(null)

// Payment modal
const showPayment = ref(false)
const paying = ref(false)
const payError = ref('')
const today = new Date().toISOString().split('T')[0]
const payForm = ref({ amount: '', mode: 'cash', payment_date: today, reference: '', notes: '' })

const statusBadge = (s: string) => ({
  draft: 'badge-gray', confirmed: 'badge-blue',
  partially_paid: 'badge-yellow', paid: 'badge-green', cancelled: 'badge-red',
}[s] ?? 'badge-gray')

async function loadInvoice() {
  const { data } = await api.get(`/sales-invoices/${route.params.id}`)
  invoice.value = data
}

async function cancel() {
  if (!confirm(`Cancel invoice ${invoice.value?.invoice_number}? This will reverse stock.`)) return
  await api.post(`/sales-invoices/${route.params.id}/cancel`)
  router.push('/sales')
}

async function recordPayment() {
  paying.value = true
  payError.value = ''
  try {
    const { data } = await api.post(`/sales-invoices/${route.params.id}/payment`, payForm.value)
    invoice.value = data.invoice
    showPayment.value = false
    payForm.value = { amount: '', mode: 'cash', payment_date: today, reference: '', notes: '' }
  } catch (e: any) {
    payError.value = e.response?.data?.message ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ') ?? 'Error recording payment.'
  } finally { paying.value = false }
}

function printInvoice() {
  window.print()
}

function whatsapp() {
  if (!invoice.value) return
  const inv  = invoice.value
  const msg  = `*GST Invoice from ${auth.user?.company?.name}*\n\nInvoice No: ${inv.invoice_number}\nDate: ${inv.invoice_date}\nCustomer: ${inv.customer?.name}\n\n*Amount: ₹${Number(inv.total_amount).toLocaleString('en-IN')}*\nPaid: ₹${Number(inv.paid_amount).toLocaleString('en-IN')}\nBalance: ₹${Number(inv.balance_amount).toLocaleString('en-IN')}\n\nFor details, please contact us.\nGenerated by OpenVyapar ERP`
  window.open(`https://wa.me/?text=${encodeURIComponent(msg)}`, '_blank')
}

onMounted(() => loadInvoice())
</script>

<style>
@media print {
  /* Hide everything using visibility so the DOM tree stays intact */
  body * { visibility: hidden !important; }

  /* Show only the invoice card and all its children */
  #printable-invoice,
  #printable-invoice * { visibility: visible !important; }

  #printable-invoice {
    position: fixed !important;
    top: 0; left: 0;
    width: 100%;
    background: white !important;
    color: black !important;
    font-size: 12px;
    padding: 24px;
    box-sizing: border-box;
  }

  #printable-invoice * { color: black !important; border-color: #e5e7eb !important; }
  #printable-invoice .bg-gray-50,
  #printable-invoice .bg-blue-50,
  #printable-invoice [class*="dark:bg"] { background: #f9fafb !important; }
  #printable-invoice thead tr { background: #1e293b !important; }
  #printable-invoice thead th { color: white !important; }
}
</style>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
