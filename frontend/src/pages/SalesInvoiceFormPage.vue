<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <div class="card p-6 space-y-5">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white">New Sales Invoice</h2>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="label">Customer *</label>
          <select v-model="form.customer_id" class="input" required @change="onCustomerChange">
            <option value="">Select Customer</option>
            <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="label">Invoice Date *</label>
          <input v-model="form.invoice_date" type="date" class="input" required />
        </div>
        <div>
          <label class="label">Due Date</label>
          <input v-model="form.due_date" type="date" class="input" />
        </div>
        <div>
          <label class="label">Payment Mode</label>
          <select v-model="form.payment_mode" class="input">
            <option value="">– Select –</option>
            <option value="cash">Cash</option>
            <option value="upi">UPI</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="cheque">Cheque</option>
            <option value="card">Card</option>
          </select>
        </div>
        <div>
          <label class="label">UPI / Ref No.</label>
          <input v-model="form.upi_ref" class="input" />
        </div>
        <div>
          <label class="label">Notes</label>
          <input v-model="form.notes" class="input" />
        </div>
      </div>

      <!-- Items -->
      <div>
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-medium text-gray-700 dark:text-gray-300">Items</h3>
          <button type="button" class="btn-secondary text-xs" @click="addItem">+ Add Item</button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-500">Product</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Qty</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Rate ₹</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Disc%</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">GST%</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Total ₹</th>
                <th class="px-3 py-2"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in form.items" :key="idx" class="border-t border-gray-100 dark:border-gray-800">
                <td class="px-3 py-2">
                  <select v-model="item.product_id" class="input text-xs" @change="onProductSelect(idx)">
                    <option value="">Select Product</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </td>
                <td class="px-3 py-2"><input v-model.number="item.quantity" type="number" step="0.001" min="0.001" class="input text-right w-20" @input="recalc" /></td>
                <td class="px-3 py-2"><input v-model.number="item.rate" type="number" step="0.01" min="0" class="input text-right w-24" @input="recalc" /></td>
                <td class="px-3 py-2"><input v-model.number="item.discount_pct" type="number" step="0.01" min="0" max="100" class="input text-right w-16" @input="recalc" /></td>
                <td class="px-3 py-2">
                  <select v-model.number="item.gst_rate" class="input text-xs w-16" @change="recalc">
                    <option v-for="r in gstRates" :key="r" :value="r">{{ r }}</option>
                  </select>
                </td>
                <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-white">₹{{ itemTotal(item).toFixed(2) }}</td>
                <td class="px-3 py-2 text-center"><button type="button" class="text-red-400 hover:text-red-600 text-xs" @click="form.items.splice(idx, 1); recalc()">✕</button></td>
              </tr>
              <tr v-if="!form.items.length"><td colspan="7" class="px-3 py-4 text-center text-gray-400 text-xs">Add items above.</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Totals -->
        <div class="mt-4 flex justify-end">
          <div class="w-64 space-y-1 text-sm">
            <div class="flex justify-between text-gray-600 dark:text-gray-400"><span>Subtotal</span><span>₹{{ totals.subtotal.toFixed(2) }}</span></div>
            <div class="flex justify-between text-gray-600 dark:text-gray-400"><span>Discount</span><span>-₹{{ totals.discount.toFixed(2) }}</span></div>
            <div class="flex justify-between text-gray-600 dark:text-gray-400"><span>Taxable</span><span>₹{{ totals.taxable.toFixed(2) }}</span></div>
            <div class="flex justify-between text-gray-600 dark:text-gray-400"><span>CGST</span><span>₹{{ totals.cgst.toFixed(2) }}</span></div>
            <div class="flex justify-between text-gray-600 dark:text-gray-400"><span>SGST</span><span>₹{{ totals.sgst.toFixed(2) }}</span></div>
            <div class="flex justify-between text-gray-600 dark:text-gray-400"><span>IGST</span><span>₹{{ totals.igst.toFixed(2) }}</span></div>
            <div class="flex justify-between font-bold text-gray-900 dark:text-white border-t border-gray-200 dark:border-gray-700 pt-1 mt-1">
              <span>Total</span><span>₹{{ totals.total.toFixed(2) }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="error" class="text-red-600 text-sm bg-red-50 dark:bg-red-900/20 px-3 py-2 rounded-lg">{{ error }}</div>

      <div class="flex gap-3">
        <button class="btn-primary" :disabled="saving" @click="submit">{{ saving ? 'Saving...' : 'Create Invoice' }}</button>
        <RouterLink to="/sales" class="btn-secondary">Cancel</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '../api/client'

const router = useRouter()
const customers = ref<any[]>([])
const products = ref<any[]>([])
const saving = ref(false)
const error = ref('')
const gstRates = [0, 0.25, 3, 5, 12, 18, 28]

const form = reactive({
  customer_id: '',
  invoice_date: new Date().toISOString().slice(0, 10),
  due_date: '',
  payment_mode: '',
  upi_ref: '',
  notes: '',
  items: [] as any[],
})

function addItem() {
  form.items.push({ product_id: '', quantity: 1, rate: 0, discount_pct: 0, gst_rate: 18 })
}

function onProductSelect(idx: number) {
  const p = products.value.find(p => p.id === form.items[idx].product_id)
  if (p) {
    form.items[idx].rate = p.selling_price
    form.items[idx].gst_rate = Number(p.gst_rate)
    recalc()
  }
}

function onCustomerChange() {}

function itemTotal(item: any) {
  const sub = item.quantity * item.rate
  const disc = sub * (item.discount_pct ?? 0) / 100
  const taxable = sub - disc
  return taxable + taxable * item.gst_rate / 100
}

function recalc() {} // totals are computed

const totals = computed(() => {
  let subtotal = 0, discount = 0, taxable = 0, cgst = 0, sgst = 0, igst = 0
  for (const item of form.items) {
    const sub = item.quantity * item.rate
    const disc = sub * (item.discount_pct ?? 0) / 100
    const tax = sub - disc
    const gstAmt = tax * item.gst_rate / 100
    subtotal += sub; discount += disc; taxable += tax
    // simplified: all intra for preview
    cgst += gstAmt / 2; sgst += gstAmt / 2
  }
  return { subtotal, discount, taxable, cgst, sgst, igst, total: taxable + cgst + sgst + igst }
})

async function submit() {
  saving.value = true; error.value = ''
  try {
    const { data } = await api.post('/sales-invoices', form)
    router.push(`/sales/${data.id}`)
  } catch (e: any) {
    const errs = e.response?.data?.errors
    error.value = errs ? Object.values(errs).flat().join(', ') : (e.response?.data?.message ?? 'Error.')
  } finally { saving.value = false }
}

onMounted(async () => {
  const [c, p] = await Promise.all([api.get('/customers', { params: { active_only: 1, per_page: 500 } }), api.get('/products', { params: { active_only: 1, per_page: 500 } })])
  customers.value = c.data.data
  products.value = p.data.data
})
</script>
