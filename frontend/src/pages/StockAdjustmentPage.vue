<template>
  <div class="max-w-5xl space-y-6 animate-fade-up">

    <div class="page-header animate-fade-down">
      <div>
        <h1 class="page-title">Stock Adjustments</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Manually correct stock — damaged goods, opening stock, theft</p>
      </div>
      <button class="btn-primary" @click="showForm = true">
        <PlusIcon class="w-4 h-4" /> New Adjustment
      </button>
    </div>

    <!-- History table -->
    <div class="card overflow-hidden animate-fade-up">
      <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <p class="font-semibold text-gray-700 dark:text-gray-300 text-sm">Adjustment History</p>
      </div>
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
            <th class="table-header">Date</th>
            <th class="table-header">Product</th>
            <th class="table-header text-right">Qty Change</th>
            <th class="table-header text-right">Balance After</th>
            <th class="table-header">Reason</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
          <tr v-if="loading" v-for="n in 4" :key="n">
            <td colspan="5" class="px-4 py-3">
              <div class="shimmer h-3 w-full rounded-full"></div>
            </td>
          </tr>
          <tr v-else v-for="r in history" :key="r.id" class="table-row">
            <td class="table-cell text-xs text-gray-500">{{ r.transacted_at?.slice(0,10) }}</td>
            <td class="table-cell">
              <p class="font-semibold text-gray-900 dark:text-white">{{ r.product?.name }}</p>
              <p class="text-xs text-gray-400 font-mono">{{ r.product?.sku }}</p>
            </td>
            <td class="table-cell text-right">
              <span class="font-bold text-base" :class="r.quantity > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                {{ r.quantity > 0 ? '+' : '' }}{{ r.quantity }}
              </span>
            </td>
            <td class="table-cell text-right font-semibold text-gray-700 dark:text-gray-300">{{ r.balance_after }}</td>
            <td class="table-cell text-gray-500 text-xs max-w-xs truncate">{{ r.note }}</td>
          </tr>
          <tr v-if="!loading && !history.length">
            <td colspan="5" class="py-14 text-center">
              <ArchiveIcon class="w-9 h-9 text-gray-300 dark:text-gray-600 mx-auto mb-2" />
              <p class="text-sm text-gray-400">No adjustments yet</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- New Adjustment Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showForm = false"></div>
          <div class="relative card w-full max-w-md p-6 shadow-2xl animate-scale-in">
            <div class="flex items-center justify-between mb-5">
              <h3 class="text-lg font-bold text-gray-900 dark:text-white">New Stock Adjustment</h3>
              <button @click="showForm = false" class="btn-icon"><XIcon class="w-4 h-4" /></button>
            </div>

            <form @submit.prevent="save" class="space-y-4">
              <div>
                <label class="label">Product *</label>
                <select v-model="form.product_id" class="input" required>
                  <option value="">Select product…</option>
                  <option v-for="p in products" :key="p.id" :value="p.id">
                    {{ p.name }} (Stock: {{ p.opening_stock ?? 0 }})
                  </option>
                </select>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="label">Quantity Change *</label>
                  <input v-model="form.quantity" type="number" step="0.001" class="input" required
                         placeholder="+10 or -5" />
                  <p class="text-xs text-gray-400 mt-1">Use negative to reduce stock</p>
                </div>
                <div>
                  <label class="label">Date *</label>
                  <input v-model="form.date" type="date" class="input" required />
                </div>
              </div>
              <div>
                <label class="label">Reason *</label>
                <select v-model="form.reason" class="input" required>
                  <option value="">Select reason…</option>
                  <option value="Opening Stock">Opening Stock Entry</option>
                  <option value="Damaged Goods">Damaged / Expired Goods</option>
                  <option value="Stock Count Correction">Stock Count Correction</option>
                  <option value="Theft / Loss">Theft / Loss</option>
                  <option value="Returned to Supplier">Returned to Supplier</option>
                  <option value="Sample / Internal Use">Sample / Internal Use</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div>
                <label class="label">Note</label>
                <textarea v-model="form.note" class="input resize-none" rows="2" placeholder="Optional details…" />
              </div>

              <div v-if="error" class="flex items-center gap-2 text-rose-600 text-sm bg-rose-50 dark:bg-rose-900/20 px-3 py-2 rounded-xl">
                <AlertCircleIcon class="w-4 h-4 flex-shrink-0" /> {{ error }}
              </div>

              <div class="flex gap-2 pt-1">
                <button type="submit" class="btn-primary flex-1 justify-center" :disabled="saving">
                  <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                  </svg>
                  <CheckIcon v-else class="w-4 h-4" />
                  {{ saving ? 'Saving…' : 'Save Adjustment' }}
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
import { ref, onMounted } from 'vue'
import api from '../api/client'
import { PlusIcon, XIcon, CheckIcon, AlertCircleIcon, ArchiveIcon } from 'lucide-vue-next'

const history  = ref<any[]>([])
const products = ref<any[]>([])
const loading  = ref(true)
const showForm = ref(false)
const saving   = ref(false)
const error    = ref('')

const today = new Date().toISOString().split('T')[0]
const form  = ref({ product_id: '', quantity: '', reason: '', note: '', date: today })

async function load() {
  loading.value = true
  const [adj, prods] = await Promise.all([
    api.get('/stock-adjustments'),
    api.get('/products', { params: { per_page: 200 } }),
  ])
  history.value  = adj.data.data ?? adj.data
  products.value = (prods.data.data ?? prods.data).filter((p: any) => p.track_inventory)
  loading.value  = false
}

async function save() {
  saving.value = true
  error.value  = ''
  try {
    await api.post('/stock-adjustments', form.value)
    showForm.value = false
    form.value = { product_id: '', quantity: '', reason: '', note: '', date: today }
    await load()
  } catch (e: any) {
    error.value = e.response?.data?.message ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ') ?? 'Error saving adjustment.'
  } finally { saving.value = false }
}

onMounted(() => load())
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
