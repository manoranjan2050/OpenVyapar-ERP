<template>
  <div class="max-w-2xl animate-fade-up">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-6 animate-fade-down">
      <RouterLink to="/products" class="btn-icon">
        <ArrowLeftIcon class="w-4 h-4" />
      </RouterLink>
      <div>
        <h1 class="page-title">{{ isEdit ? 'Edit Product' : 'New Product' }}</h1>
        <p class="text-sm text-gray-400 mt-0.5">{{ isEdit ? 'Update product details' : 'Add a new product to your catalogue' }}</p>
      </div>
    </div>

    <form @submit.prevent="submit" class="space-y-5">

      <!-- Basic info -->
      <div class="card p-6 space-y-4">
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-2">
          <PackageIcon class="w-4 h-4 text-blue-500" /> Basic Information
        </h2>
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="label">Product Name *</label>
            <input v-model="form.name" class="input" required placeholder="e.g. Basmati Rice 1kg" />
          </div>
          <div>
            <label class="label">SKU Code</label>
            <input v-model="form.sku" class="input font-mono" placeholder="RICE-001" />
          </div>
          <div>
            <label class="label">Barcode / EAN</label>
            <input v-model="form.barcode" class="input font-mono" placeholder="8901234567890" />
          </div>
          <div>
            <label class="label">HSN Code</label>
            <input v-model="form.hsn_code" class="input font-mono" maxlength="8" placeholder="10063090" />
          </div>
          <div>
            <label class="label">GST Rate *</label>
            <select v-model="form.gst_rate" class="input">
              <option v-for="r in gstRates" :key="r" :value="r">{{ r }}%</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="label">Description</label>
            <textarea v-model="form.description" class="input resize-none" rows="2" placeholder="Optional product description…" />
          </div>
        </div>
      </div>

      <!-- Pricing -->
      <div class="card p-6 space-y-4">
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-2">
          <IndianRupeeIcon class="w-4 h-4 text-emerald-500" /> Pricing
        </h2>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="label">Purchase Price ₹ *</label>
            <input v-model="form.purchase_price" type="number" step="0.01" min="0" class="input" required placeholder="0.00" />
          </div>
          <div>
            <label class="label">Selling Price ₹ *</label>
            <input v-model="form.selling_price" type="number" step="0.01" min="0" class="input" required placeholder="0.00" />
          </div>
          <div>
            <label class="label">MRP ₹</label>
            <input v-model="form.mrp" type="number" step="0.01" min="0" class="input" placeholder="0.00" />
          </div>
        </div>
        <!-- Margin preview -->
        <div v-if="form.purchase_price > 0 && form.selling_price > 0"
             class="flex items-center gap-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20">
          <div class="text-center">
            <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">Margin</p>
            <p class="text-lg font-extrabold text-emerald-700 dark:text-emerald-300">{{ marginPct }}%</p>
          </div>
          <div class="h-8 w-px bg-emerald-200 dark:bg-emerald-800"></div>
          <div class="text-xs text-emerald-600 dark:text-emerald-400">
            Profit per unit: <strong>₹{{ marginAmt }}</strong>
          </div>
        </div>
      </div>

      <!-- Inventory -->
      <div class="card p-6 space-y-4">
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-2">
          <WarehouseIcon class="w-4 h-4 text-violet-500" /> Inventory
        </h2>
        <div class="flex items-center gap-3 p-3 rounded-xl bg-blue-50 dark:bg-blue-900/20 mb-2">
          <label class="relative inline-flex items-center cursor-pointer">
            <input v-model="form.track_inventory" type="checkbox" class="sr-only peer" />
            <div class="w-10 h-6 bg-gray-300 rounded-full peer-checked:bg-blue-600 transition-colors duration-200
                        after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white
                        after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-4"></div>
          </label>
          <div>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Track Inventory</p>
            <p class="text-xs text-gray-400">Enable stock deduction on sales</p>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4" v-if="form.track_inventory">
          <div>
            <label class="label">Opening Stock</label>
            <input v-model="form.opening_stock" type="number" min="0" class="input" placeholder="0" />
          </div>
          <div>
            <label class="label">Low Stock Alert At</label>
            <input v-model="form.low_stock_alert" type="number" min="0" class="input" placeholder="5" />
          </div>
        </div>
      </div>

      <!-- Error -->
      <div v-if="error"
           class="flex items-center gap-2 text-rose-600 text-sm bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 px-4 py-3 rounded-xl">
        <AlertCircleIcon class="w-4 h-4 flex-shrink-0" />
        {{ error }}
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3 pb-6">
        <button type="submit" class="btn-primary" :disabled="saving">
          <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          <SaveIcon v-else class="w-4 h-4" />
          {{ saving ? 'Saving…' : isEdit ? 'Update Product' : 'Save Product' }}
        </button>
        <RouterLink to="/products" class="btn-secondary">Cancel</RouterLink>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '../api/client'
import { ArrowLeftIcon, PackageIcon, IndianRupeeIcon, WarehouseIcon, SaveIcon, AlertCircleIcon } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => !!route.params.id)
const saving = ref(false)
const error = ref('')

const gstRates = ['0', '0.25', '3', '5', '12', '18', '28']

const form = ref({
  name: '', sku: '', barcode: '', hsn_code: '', gst_rate: '18',
  purchase_price: 0, selling_price: 0, mrp: null as number | null,
  opening_stock: 0, low_stock_alert: 5, description: '', track_inventory: true,
})

const marginPct = computed(() => {
  const p = Number(form.value.purchase_price)
  const s = Number(form.value.selling_price)
  if (!p) return 0
  return ((s - p) / p * 100).toFixed(1)
})
const marginAmt = computed(() => (Number(form.value.selling_price) - Number(form.value.purchase_price)).toFixed(2))

onMounted(async () => {
  if (isEdit.value) {
    const { data } = await api.get(`/products/${route.params.id}`)
    Object.assign(form.value, data)
  }
})

async function submit() {
  saving.value = true
  error.value = ''
  try {
    if (isEdit.value) {
      await api.put(`/products/${route.params.id}`, form.value)
    } else {
      await api.post('/products', form.value)
    }
    router.push('/products')
  } catch (e: any) {
    const errs = e.response?.data?.errors
    error.value = errs ? Object.values(errs).flat().join(', ') : (e.response?.data?.message ?? 'Error saving.')
  } finally { saving.value = false }
}
</script>
