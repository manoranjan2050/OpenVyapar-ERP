<template>
  <div class="space-y-5">

    <!-- Header -->
    <div class="page-header animate-fade-down">
      <div>
        <h1 class="page-title">Inventory</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Real-time stock levels across all products</p>
      </div>
      <div v-if="lowStockCount > 0" class="flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800">
        <AlertTriangleIcon class="w-4 h-4 text-rose-600 dark:text-rose-400" />
        <span class="text-sm font-semibold text-rose-700 dark:text-rose-400">{{ lowStockCount }} items low stock</span>
      </div>
    </div>

    <!-- Summary bars -->
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
          <p class="text-2xl font-extrabold" :class="lowStockCount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white'">
            {{ lowStockCount }}
          </p>
          <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide">Low Stock</p>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden animate-fade-up delay-75">
      <table class="w-full">
        <thead>
          <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
            <th class="table-header">Product</th>
            <th class="table-header">Category</th>
            <th class="table-header text-right">Opening Stock</th>
            <th class="table-header text-right">Alert Level</th>
            <th class="table-header text-center">Tracking</th>
            <th class="table-header text-center">Stock Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
          <tr v-for="(p, i) in products" :key="p.id"
              class="table-row" :class="isLow(p) ? 'bg-rose-50/30 dark:bg-rose-900/5' : ''">
            <td class="table-cell">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                     :class="avatarColor(i)">
                  {{ p.name.charAt(0) }}
                </div>
                <div>
                  <p class="font-semibold text-gray-900 dark:text-white">{{ p.name }}</p>
                  <p class="text-xs font-mono text-gray-400">{{ p.sku ?? '–' }}</p>
                </div>
              </div>
            </td>
            <td class="table-cell"><span class="badge-gray">{{ p.category?.name ?? '–' }}</span></td>
            <td class="table-cell text-right font-bold text-gray-900 dark:text-white">
              {{ p.opening_stock ?? 0 }} <span class="text-xs text-gray-400 font-normal">{{ p.unit?.name ?? '' }}</span>
            </td>
            <td class="table-cell text-right" :class="isLow(p) ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-gray-500'">
              {{ p.low_stock_alert ?? '–' }}
            </td>
            <td class="table-cell text-center">
              <span :class="p.track_inventory ? 'badge-green' : 'badge-gray'">
                {{ p.track_inventory ? 'Tracked' : 'Not tracked' }}
              </span>
            </td>
            <td class="table-cell text-center">
              <div v-if="p.track_inventory" class="flex flex-col items-center gap-1">
                <span :class="isLow(p) ? 'badge-red' : 'badge-green'">
                  <span class="w-1.5 h-1.5 rounded-full inline-block mr-1"
                        :class="isLow(p) ? 'bg-rose-500' : 'bg-emerald-500'"></span>
                  {{ isLow(p) ? 'Low Stock' : 'In Stock' }}
                </span>
                <!-- Stock bar -->
                <div class="w-20 h-1 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                  <div class="h-full rounded-full transition-all duration-500"
                       :class="isLow(p) ? 'bg-rose-500' : 'bg-emerald-500'"
                       :style="{ width: stockPct(p) }"></div>
                </div>
              </div>
              <span v-else class="text-xs text-gray-400">–</span>
            </td>
          </tr>
          <tr v-if="!products.length">
            <td colspan="6" class="py-12 text-center text-gray-400">No products found.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '../api/client'
import { PackageIcon, AlertTriangleIcon, CheckCircleIcon } from 'lucide-vue-next'

const products = ref<any[]>([])

const isLow = (p: any) => p.track_inventory && (p.opening_stock ?? 0) <= (p.low_stock_alert ?? 0)
const stockPct = (p: any) => {
  const max = Math.max(p.low_stock_alert * 3, 1)
  return Math.min(100, Math.round((p.opening_stock / max) * 100)) + '%'
}

const lowStockCount = computed(() => products.value.filter(isLow).length)
const trackedCount = computed(() => products.value.filter(p => p.track_inventory).length)

const colors = ['bg-blue-500','bg-emerald-500','bg-violet-500','bg-orange-500','bg-rose-500','bg-teal-500']
const avatarColor = (i: number) => colors[i % colors.length]

onMounted(async () => {
  const { data } = await api.get('/products', { params: { per_page: 500 } })
  products.value = data.data
})
</script>
