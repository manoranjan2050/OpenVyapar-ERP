<template>
  <div class="space-y-5">

    <!-- Header -->
    <div class="page-header animate-fade-down">
      <div>
        <h1 class="page-title">Inventory</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Real-time stock across all products</p>
      </div>
      <div class="flex items-center gap-2">
        <div v-if="lowStockCount > 0" class="flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800">
          <AlertTriangleIcon class="w-4 h-4 text-rose-600 dark:text-rose-400" />
          <span class="text-sm font-semibold text-rose-700 dark:text-rose-400">{{ lowStockCount }} low stock</span>
        </div>
        <!-- Tab: inventory / damage log -->
        <div class="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl">
          <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
            :class="activeTab === t.key ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'">
            {{ t.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-3 gap-4 animate-fade-up">
      <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
          <PackageIcon class="w-6 h-6 text-blue-600 dark:text-blue-400" />
        </div>
        <div>
          <p class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ products.length }}</p>
          <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide">Total Products</p>
        </div>
      </div>
      <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
          <CheckCircleIcon class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
        </div>
        <div>
          <p class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ trackedCount }}</p>
          <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide">Tracked</p>
        </div>
      </div>
      <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-900/20 flex items-center justify-center">
          <AlertTriangleIcon class="w-6 h-6 text-rose-600 dark:text-rose-400" />
        </div>
        <div>
          <p class="text-2xl font-extrabold" :class="lowStockCount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white'">{{ lowStockCount }}</p>
          <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide">Low Stock</p>
        </div>
      </div>
    </div>

    <!-- === STOCK TABLE === -->
    <div v-if="activeTab === 'stock'" class="card overflow-hidden animate-fade-up delay-75">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
            <th class="table-header">Product</th>
            <th class="table-header">Category</th>
            <th class="table-header text-right">Current Stock</th>
            <th class="table-header text-right">Alert Level</th>
            <th class="table-header text-center">Status</th>
            <th class="table-header text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
          <tr v-for="(p, i) in products" :key="p.id" class="table-row group"
              :class="isLow(p) ? 'bg-rose-50/30 dark:bg-rose-900/5' : ''">
            <td class="table-cell">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold flex-shrink-0" :class="avatarColor(i)">
                  {{ p.name.charAt(0) }}
                </div>
                <div>
                  <p class="font-semibold text-gray-900 dark:text-white">{{ p.name }}</p>
                  <p class="text-xs font-mono text-gray-400">{{ p.sku ?? '–' }}</p>
                </div>
              </div>
            </td>
            <td class="table-cell"><span class="badge-gray">{{ p.category?.name ?? '–' }}</span></td>
            <td class="table-cell text-right">
              <span class="text-lg font-extrabold" :class="isLow(p) ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white'">
                {{ currentStock(p) }}
              </span>
              <span class="text-xs text-gray-400 ml-1">{{ p.unit?.short_name ?? '' }}</span>
            </td>
            <td class="table-cell text-right" :class="isLow(p) ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-gray-500'">
              {{ p.low_stock_alert ?? '–' }}
            </td>
            <td class="table-cell text-center">
              <div v-if="p.track_inventory">
                <span :class="isLow(p) ? 'badge-red' : 'badge-green'" class="capitalize">
                  {{ isLow(p) ? 'Low Stock' : 'In Stock' }}
                </span>
                <div class="w-20 h-1 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden mx-auto mt-1">
                  <div class="h-full rounded-full" :class="isLow(p) ? 'bg-rose-500' : 'bg-emerald-500'" :style="{ width: stockPct(p) }"></div>
                </div>
              </div>
              <span v-else class="text-xs text-gray-400">Not tracked</span>
            </td>
            <td class="table-cell text-right">
              <div v-if="p.track_inventory" class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-all">
                <button @click="openHistory(p)" class="text-xs px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-blue-100 dark:hover:bg-blue-900/30 font-semibold">
                  History
                </button>
                <button @click="openAdjust(p, 'adjustment')" class="text-xs px-2 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 hover:bg-blue-100 font-semibold">
                  Adjust
                </button>
                <button @click="openAdjust(p, 'damage')" class="text-xs px-2 py-1 rounded-lg bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400 hover:bg-rose-100 font-semibold">
                  Damage
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!products.length">
            <td colspan="6" class="py-12 text-center text-gray-400">No products found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- === DAMAGE / LOSS LOG === -->
    <div v-if="activeTab === 'damage'" class="card overflow-hidden animate-fade-up">
      <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <p class="font-semibold text-gray-700 dark:text-gray-300 text-sm">Damage &amp; Loss Log</p>
        <div class="flex gap-2">
          <button v-for="t in ['damage','loss']" :key="t" @click="damageTypeFilter = t"
            class="px-3 py-1 rounded-lg text-xs font-semibold transition-all"
            :class="damageTypeFilter === t ? 'bg-rose-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">
            {{ t.charAt(0).toUpperCase() + t.slice(1) }}
          </button>
        </div>
      </div>
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
            <th class="table-header">Date</th>
            <th class="table-header">Product</th>
            <th class="table-header text-right">Qty Removed</th>
            <th class="table-header text-right">Balance After</th>
            <th class="table-header">Reason / Note</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
          <tr v-if="loadingDamage"><td colspan="5" class="px-4 py-3"><div class="shimmer h-3 w-full rounded-full"></div></td></tr>
          <tr v-else v-for="r in damageLog" :key="r.id" class="table-row">
            <td class="table-cell text-xs text-gray-500">{{ r.transacted_at?.slice(0,10) }}</td>
            <td class="table-cell font-semibold text-gray-900 dark:text-white">{{ r.product?.name }}</td>
            <td class="table-cell text-right font-bold text-rose-600 dark:text-rose-400">{{ Math.abs(r.quantity) }}</td>
            <td class="table-cell text-right text-gray-700 dark:text-gray-300">{{ r.balance_after }}</td>
            <td class="table-cell text-xs text-gray-500 max-w-xs truncate">{{ r.note }}</td>
          </tr>
          <tr v-if="!loadingDamage && !damageLog.length">
            <td colspan="5" class="py-12 text-center text-gray-400">No {{ damageTypeFilter }} records found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- === ADJUST / DAMAGE MODAL === -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="adjustModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="adjustModal.show = false"></div>
          <div class="relative card w-full max-w-md p-6 shadow-2xl animate-scale-in">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
              {{ adjustModal.type === 'damage' ? 'Record Damage / Loss' : 'Stock Adjustment' }}
              <span class="text-blue-600 dark:text-blue-400 ml-2 text-sm">{{ adjustModal.product?.name }}</span>
            </h3>
            <p class="text-sm text-gray-500 mb-4">Current Stock: <strong>{{ adjustModal.currentStock }}</strong> {{ adjustModal.product?.unit?.short_name }}</p>

            <div class="space-y-3">
              <div v-if="adjustModal.type === 'adjustment'" class="grid grid-cols-2 gap-3">
                <div>
                  <label class="label">Change (+/-)</label>
                  <input v-model.number="adjustForm.quantity" type="number" class="input" placeholder="+10 or -5" />
                </div>
                <div>
                  <label class="label">Date</label>
                  <input v-model="adjustForm.date" type="date" class="input" />
                </div>
              </div>
              <div v-else class="grid grid-cols-2 gap-3">
                <div>
                  <label class="label">Qty Damaged/Lost</label>
                  <input v-model.number="adjustForm.quantity" type="number" min="1" class="input" placeholder="e.g. 5" />
                </div>
                <div>
                  <label class="label">Date</label>
                  <input v-model="adjustForm.date" type="date" class="input" />
                </div>
              </div>
              <div>
                <label class="label">Reason</label>
                <select v-if="adjustModal.type === 'damage'" v-model="adjustForm.reason" class="input">
                  <option value="rat damage">Rat / Pest Damage</option>
                  <option value="water damage">Water Damage</option>
                  <option value="fire damage">Fire Damage</option>
                  <option value="expired">Expired / Spoiled</option>
                  <option value="breakage">Breakage</option>
                  <option value="theft">Theft / Stolen</option>
                  <option value="other loss">Other Loss</option>
                </select>
                <select v-else v-model="adjustForm.reason" class="input">
                  <option value="stock count">Physical Stock Count</option>
                  <option value="opening stock">Opening Stock Correction</option>
                  <option value="return to supplier">Return to Supplier</option>
                  <option value="sample">Sample / Demo</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div>
                <label class="label">Additional Note</label>
                <input v-model="adjustForm.note" class="input" placeholder="Optional details..." />
              </div>
              <div v-if="adjustError" class="text-xs text-rose-600 bg-rose-50 dark:bg-rose-900/20 px-3 py-2 rounded-xl">{{ adjustError }}</div>
            </div>

            <div class="flex gap-3 mt-5">
              <button @click="submitAdjust" :disabled="adjustSaving" class="btn-primary flex-1 justify-center">
                {{ adjustSaving ? 'Saving…' : (adjustModal.type === 'damage' ? 'Record Damage' : 'Save Adjustment') }}
              </button>
              <button @click="adjustModal.show = false" class="btn-secondary">Cancel</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- === HISTORY MODAL === -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="histModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="histModal.show = false"></div>
          <div class="relative card w-full max-w-2xl p-6 shadow-2xl animate-scale-in max-h-[80vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                Stock History — {{ histModal.product?.name }}
                <span class="text-sm font-normal text-gray-500 ml-2">Current: {{ histModal.currentStock }}</span>
              </h3>
              <button @click="histModal.show = false" class="btn-icon"><XIcon class="w-4 h-4" /></button>
            </div>
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-100 dark:border-gray-800">
                  <th class="table-header">Date</th>
                  <th class="table-header">Type</th>
                  <th class="table-header text-right">Qty</th>
                  <th class="table-header text-right">Balance</th>
                  <th class="table-header">Note</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
                <tr v-for="r in histModal.rows" :key="r.id" class="table-row">
                  <td class="table-cell text-xs text-gray-500">{{ r.transacted_at?.slice(0,10) }}</td>
                  <td class="table-cell">
                    <span :class="{
                      'badge-green': r.type === 'in',
                      'badge-red': r.type === 'out' || r.type === 'damage' || r.type === 'loss',
                      'badge-blue': r.type === 'adjustment',
                      'badge-gray': !['in','out','damage','loss','adjustment'].includes(r.type)
                    }" class="capitalize">{{ r.type }}</span>
                  </td>
                  <td class="table-cell text-right font-bold" :class="r.quantity >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                    {{ r.quantity >= 0 ? '+' : '' }}{{ r.quantity }}
                  </td>
                  <td class="table-cell text-right text-gray-700 dark:text-gray-300">{{ r.balance_after }}</td>
                  <td class="table-cell text-xs text-gray-500 max-w-xs truncate">{{ r.note }}</td>
                </tr>
                <tr v-if="!histModal.rows.length">
                  <td colspan="5" class="py-8 text-center text-gray-400 text-sm">No stock movements yet.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import api from '../api/client'
import { PackageIcon, AlertTriangleIcon, CheckCircleIcon, XIcon } from 'lucide-vue-next'

const products = ref<any[]>([])
const activeTab = ref('stock')
const tabs = [
  { key: 'stock',  label: 'Stock Levels' },
  { key: 'damage', label: 'Damage / Loss' },
]

const currentStock = (p: any) => p.current_stock ?? p.opening_stock ?? 0
const isLow = (p: any) => p.track_inventory && currentStock(p) <= (p.low_stock_alert ?? 0)
const stockPct = (p: any) => {
  const max = Math.max((p.low_stock_alert ?? 0) * 3, 1)
  return Math.min(100, Math.round((currentStock(p) / max) * 100)) + '%'
}
const lowStockCount = computed(() => products.value.filter(isLow).length)
const trackedCount  = computed(() => products.value.filter(p => p.track_inventory).length)
const colors = ['bg-blue-500','bg-emerald-500','bg-violet-500','bg-orange-500','bg-rose-500','bg-teal-500']
const avatarColor = (i: number) => colors[i % colors.length]

// Damage / Loss log
const damageLog      = ref<any[]>([])
const loadingDamage  = ref(false)
const damageTypeFilter = ref('damage')

watch([activeTab, damageTypeFilter], async () => {
  if (activeTab.value !== 'damage') return
  loadingDamage.value = true
  const { data } = await api.get('/stock-adjustments', { params: { type: damageTypeFilter.value, per_page: 100 } })
  damageLog.value = data.data
  loadingDamage.value = false
})

// Adjust modal
const adjustModal = ref({ show: false, type: 'adjustment', product: null as any, currentStock: 0 })
const adjustForm  = ref({ quantity: 0, date: new Date().toISOString().slice(0,10), reason: '', note: '' })
const adjustError = ref('')
const adjustSaving = ref(false)

function openAdjust(p: any, type: 'adjustment' | 'damage') {
  adjustModal.value = { show: true, type, product: p, currentStock: currentStock(p) }
  adjustForm.value  = {
    quantity: type === 'damage' ? 1 : 0,
    date: new Date().toISOString().slice(0,10),
    reason: type === 'damage' ? 'rat damage' : 'stock count',
    note: '',
  }
  adjustError.value = ''
}

async function submitAdjust() {
  adjustSaving.value = true
  adjustError.value  = ''
  try {
    const qty = adjustModal.value.type === 'damage'
      ? -Math.abs(adjustForm.value.quantity)
      : adjustForm.value.quantity

    await api.post('/stock-adjustments', {
      product_id: adjustModal.value.product.id,
      quantity:   qty,
      reason:     adjustForm.value.reason,
      note:       adjustForm.value.note,
      date:       adjustForm.value.date,
      type:       adjustModal.value.type,
    })
    adjustModal.value.show = false
    // Refresh products list
    await loadProducts()
  } catch (e: any) {
    adjustError.value = e.response?.data?.message ?? 'Save failed.'
  } finally {
    adjustSaving.value = false
  }
}

// History modal
const histModal = ref({ show: false, product: null as any, rows: [] as any[], currentStock: 0 })

async function openHistory(p: any) {
  histModal.value = { show: true, product: p, rows: [], currentStock: currentStock(p) }
  const { data } = await api.get(`/stock-adjustments/history/${p.id}`)
  histModal.value.rows         = data.history
  histModal.value.currentStock = data.current_stock
}

async function loadProducts() {
  const { data } = await api.get('/products', { params: { per_page: 500 } })
  products.value = data.data
}

onMounted(loadProducts)
</script>
