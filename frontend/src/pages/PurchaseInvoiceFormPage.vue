<template>
  <div class="max-w-5xl mx-auto space-y-6">
    <div class="card p-6 space-y-5">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white">New Purchase Invoice</h2>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="label">Supplier *</label>
          <SearchableSelect
            v-model="form.supplier_id"
            :options="supplierOptions"
            placeholder="Search supplier…"
          >
            <template #option="{ opt }">
              <div class="font-medium text-gray-900 dark:text-white">{{ opt.label }}</div>
              <div v-if="opt.sub" class="text-xs text-gray-400">📞 {{ opt.sub }}</div>
            </template>
          </SearchableSelect>
        </div>
        <div>
          <label class="label">Supplier Invoice No.</label>
          <input v-model="form.supplier_invoice_number" class="input" />
        </div>
        <div>
          <label class="label">Invoice Date *</label>
          <input v-model="form.invoice_date" type="date" class="input" required />
        </div>
        <div>
          <label class="label">Due Date</label>
          <input v-model="form.due_date" type="date" class="input" />
        </div>
        <div class="col-span-2">
          <label class="label">Notes</label>
          <input v-model="form.notes" class="input" />
        </div>
      </div>

      <!-- Items -->
      <div>
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-medium text-gray-700 dark:text-gray-300">Items</h3>
          <div class="flex items-center gap-2">
            <RouterLink to="/products/new" target="_blank"
              class="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
              <PlusCircleIcon class="w-3.5 h-3.5" /> New Product
            </RouterLink>
            <button type="button" class="btn-secondary text-xs" @click="addItem">+ Add Item</button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-500 w-44">Product</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Qty</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Rate ₹</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Disc%</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">GST%</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500">Batch No.</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500">Lot No.</th>
                <th class="px-3 py-2 text-right font-medium text-gray-500">Total</th>
                <th class="px-3 py-2"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in form.items" :key="idx" class="border-t border-gray-100 dark:border-gray-800">
                <td class="px-3 py-2">
                  <SearchableSelect
                    v-model="item.product_id"
                    :options="productOptions"
                    placeholder="Search product…"
                    @change="onProductSelect(idx)"
                  />
                </td>
                <td class="px-3 py-2"><input v-model.number="item.quantity" type="number" step="0.001" min="0.001" class="input text-right w-20" /></td>
                <td class="px-3 py-2"><input v-model.number="item.rate" type="number" step="0.01" min="0" class="input text-right w-24" /></td>
                <td class="px-3 py-2"><input v-model.number="item.discount_pct" type="number" min="0" max="100" class="input text-right w-16" /></td>
                <td class="px-3 py-2">
                  <select v-model.number="item.gst_rate" class="input text-xs w-16">
                    <option v-for="r in gstRates" :key="r" :value="r">{{ r }}</option>
                  </select>
                </td>
                <td class="px-3 py-2"><input v-model="item.batch_no" class="input w-24" placeholder="Batch #" /></td>
                <td class="px-3 py-2"><input v-model="item.lot_no" class="input w-24" placeholder="Lot #" /></td>
                <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-white">₹{{ itemTotal(item).toFixed(2) }}</td>
                <td class="px-3 py-2 text-center"><button type="button" class="text-red-400 hover:text-red-600 text-xs" @click="form.items.splice(idx, 1)">✕</button></td>
              </tr>
              <tr v-if="!form.items.length"><td colspan="9" class="px-3 py-4 text-center text-gray-400 text-xs">Add items above.</td></tr>
            </tbody>
          </table>
        </div>
        <div class="mt-4 flex justify-end">
          <div class="w-60 space-y-1 text-sm">
            <div class="flex justify-between font-bold text-gray-900 dark:text-white border-t pt-1">
              <span>Total</span><span>₹{{ grandTotal.toFixed(2) }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="error" class="text-red-600 text-sm bg-red-50 dark:bg-red-900/20 px-3 py-2 rounded-lg">{{ error }}</div>
      <div class="flex gap-3">
        <button class="btn-primary" :disabled="saving" @click="submit">{{ saving ? 'Saving...' : 'Save Purchase' }}</button>
        <RouterLink to="/purchases" class="btn-secondary">Cancel</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { PlusCircleIcon } from 'lucide-vue-next'
import api from '../api/client'
import SearchableSelect from '../components/SearchableSelect.vue'

const router = useRouter()
const suppliers = ref<any[]>([])
const products = ref<any[]>([])
const saving = ref(false)
const error = ref('')
const gstRates = [0, 0.25, 3, 5, 12, 18, 28]

const supplierOptions = computed(() =>
  suppliers.value.map(s => ({ value: s.id, label: s.name, sub: s.phone ?? '' }))
)

const productOptions = computed(() =>
  products.value.map(p => ({ value: p.id, label: p.name, sub: p.sku ?? '' }))
)

const form = reactive({
  supplier_id: '',
  supplier_invoice_number: '',
  invoice_date: new Date().toISOString().slice(0, 10),
  due_date: '',
  notes: '',
  items: [] as any[],
})

function addItem() {
  form.items.push({ product_id: '', quantity: 1, rate: 0, discount_pct: 0, gst_rate: 18, batch_no: '', lot_no: '' })
}

function onProductSelect(idx: number) {
  const p = products.value.find(p => p.id === form.items[idx].product_id)
  if (p) {
    form.items[idx].rate = p.purchase_price
    form.items[idx].gst_rate = Number(p.gst_rate)
  }
}

function itemTotal(item: any) {
  const sub = item.quantity * item.rate
  const disc = sub * (item.discount_pct ?? 0) / 100
  const taxable = sub - disc
  return taxable + taxable * item.gst_rate / 100
}

const grandTotal = computed(() => form.items.reduce((s, item) => s + itemTotal(item), 0))

async function submit() {
  saving.value = true; error.value = ''
  try {
    await api.post('/purchase-invoices', form)
    router.push('/purchases')
  } catch (e: any) {
    const errs = e.response?.data?.errors
    error.value = errs ? Object.values(errs).flat().join(', ') : (e.response?.data?.message ?? 'Error.')
  } finally { saving.value = false }
}

onMounted(async () => {
  const [s, p] = await Promise.all([
    api.get('/suppliers', { params: { per_page: 500 } }),
    api.get('/products', { params: { active_only: 1, per_page: 500 } }),
  ])
  suppliers.value = s.data.data
  products.value = p.data.data
})
</script>
