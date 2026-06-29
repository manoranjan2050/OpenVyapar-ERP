<template>
  <div class="space-y-5">

    <!-- Header -->
    <div class="page-header animate-fade-down">
      <div>
        <h1 class="page-title">Products</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Manage your product catalogue · {{ meta.total }} total</p>
      </div>
      <div class="flex items-center gap-2">
        <button class="btn-secondary" @click="exportExcel" :disabled="!products.length">
          <DownloadIcon class="w-4 h-4" /> Export
        </button>
        <RouterLink to="/products/new" class="btn-primary">
          <PlusIcon class="w-4 h-4" /> New Product
        </RouterLink>
      </div>
    </div>

    <!-- Search + filters -->
    <div class="flex items-center gap-3 animate-fade-up">
      <div class="relative flex-1 max-w-sm">
        <SearchIcon class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
        <input v-model="search" type="text" placeholder="Search products…" class="input pl-10"
               @input="debouncedFetch" />
      </div>
      <select v-model="filterStatus" class="select w-36" @change="fetchPage(1)">
        <option value="">All Status</option>
        <option value="1">Active</option>
        <option value="0">Inactive</option>
      </select>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden animate-fade-up delay-75">
      <table class="w-full">
        <thead>
          <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
            <th class="table-header">Product</th>
            <th class="table-header">Category</th>
            <th class="table-header">HSN</th>
            <th class="table-header text-right">MRP</th>
            <th class="table-header text-right">Sale Price</th>
            <th class="table-header text-center">GST%</th>
            <th class="table-header text-center">Status</th>
            <th class="table-header w-16"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">

          <!-- Loading rows -->
          <tr v-if="loading" v-for="n in 5" :key="n">
            <td class="px-4 py-3.5" colspan="8">
              <div class="flex items-center gap-3">
                <div class="shimmer w-8 h-8 rounded-xl"></div>
                <div class="space-y-1.5 flex-1">
                  <div class="shimmer h-3 w-40 rounded-full"></div>
                  <div class="shimmer h-2.5 w-24 rounded-full"></div>
                </div>
              </div>
            </td>
          </tr>

          <tr v-else v-for="(p, i) in products" :key="p.id"
              class="table-row group" :style="`animation-delay:${i * 40}ms`">

            <td class="table-cell">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                     :class="avatarColor(i)">
                  {{ p.name.charAt(0) }}
                </div>
                <div>
                  <p class="font-semibold text-gray-900 dark:text-white">{{ p.name }}</p>
                  <p class="text-xs text-gray-400 font-mono">{{ p.sku ?? p.barcode ?? '–' }}</p>
                </div>
              </div>
            </td>
            <td class="table-cell">
              <span class="badge-gray">{{ p.category?.name ?? '–' }}</span>
            </td>
            <td class="table-cell font-mono text-xs text-gray-500 dark:text-gray-400">{{ p.hsn_code ?? '–' }}</td>
            <td class="table-cell text-right text-gray-500 dark:text-gray-400 line-through text-xs">
              {{ p.mrp ? '₹' + Number(p.mrp).toLocaleString('en-IN') : '–' }}
            </td>
            <td class="table-cell text-right font-bold text-gray-900 dark:text-white">
              ₹{{ Number(p.selling_price).toLocaleString('en-IN') }}
            </td>
            <td class="table-cell text-center">
              <span class="badge-blue">{{ p.gst_rate }}%</span>
            </td>
            <td class="table-cell text-center">
              <span :class="p.is_active ? 'badge-green' : 'badge-gray'">
                <span class="w-1.5 h-1.5 rounded-full inline-block mr-1"
                      :class="p.is_active ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                {{ p.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="table-cell text-right">
              <RouterLink :to="`/products/${p.id}/edit`"
                class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 dark:text-blue-400
                       hover:text-blue-700 dark:hover:text-blue-300 opacity-0 group-hover:opacity-100 transition-all">
                <EditIcon class="w-3.5 h-3.5" />
                Edit
              </RouterLink>
            </td>
          </tr>

          <tr v-if="!loading && !products.length">
            <td colspan="8" class="py-16 text-center">
              <div class="flex flex-col items-center gap-3">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                  <PackageIcon class="w-7 h-7 text-gray-400" />
                </div>
                <div>
                  <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No products found</p>
                  <p class="text-xs text-gray-400 mt-1">Try a different search or add a new product</p>
                </div>
                <RouterLink to="/products/new" class="btn-primary mt-1">
                  <PlusIcon class="w-4 h-4" /> Add Product
                </RouterLink>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1"
           class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/20">
        <span class="text-xs text-gray-500 dark:text-gray-400">
          Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }} products
        </span>
        <div class="flex items-center gap-2">
          <button class="btn-secondary py-1.5 px-3 text-xs" :disabled="meta.current_page === 1"
                  @click="fetchPage(meta.current_page - 1)">
            <ChevronLeftIcon class="w-3.5 h-3.5" /> Prev
          </button>
          <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 px-2">
            {{ meta.current_page }} / {{ meta.last_page }}
          </span>
          <button class="btn-secondary py-1.5 px-3 text-xs" :disabled="meta.current_page === meta.last_page"
                  @click="fetchPage(meta.current_page + 1)">
            Next <ChevronRightIcon class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import api from '../api/client'
import { PlusIcon, SearchIcon, EditIcon, PackageIcon, ChevronLeftIcon, ChevronRightIcon, DownloadIcon } from 'lucide-vue-next'
import * as XLSX from 'xlsx'

const products = ref<any[]>([])
const search = ref('')
const filterStatus = ref('')
const loading = ref(true)
const meta = ref({ current_page: 1, last_page: 1, from: 1, to: 0, total: 0 })

const colors = ['bg-blue-500','bg-emerald-500','bg-violet-500','bg-orange-500','bg-rose-500','bg-teal-500','bg-amber-500']
const avatarColor = (i: number) => colors[i % colors.length]

async function fetchPage(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/products', { params: { search: search.value, page, is_active: filterStatus.value } })
    products.value = data.data
    meta.value = data.meta ?? data
  } finally {
    loading.value = false
  }
}

const debouncedFetch = useDebounceFn(() => fetchPage(1), 300)

function exportExcel() {
  const rows = products.value.map(p => ({
    SKU: p.sku ?? '',
    Name: p.name,
    Category: p.category?.name ?? '',
    HSN: p.hsn_code ?? '',
    'GST %': p.gst_rate,
    'Purchase Price': p.purchase_price,
    'Selling Price': p.selling_price,
    MRP: p.mrp ?? '',
    'Opening Stock': p.opening_stock ?? 0,
    Status: p.is_active ? 'Active' : 'Inactive',
  }))
  const ws = XLSX.utils.json_to_sheet(rows)
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Products')
  XLSX.writeFile(wb, `products_${new Date().toISOString().slice(0,10)}.xlsx`)
}

onMounted(() => fetchPage())
</script>
