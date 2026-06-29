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
        <button class="btn-secondary" @click="downloadSample">
          <FileDownIcon class="w-4 h-4" /> Sample File
        </button>
        <button class="btn-secondary" @click="triggerImport">
          <UploadIcon class="w-4 h-4" /> Import
        </button>
        <input ref="importFileRef" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="handleImport" />
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

  <!-- Import Result Modal -->
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="importResult" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="importResult = null"></div>
        <div class="relative card w-full max-w-md p-6 shadow-2xl">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Import Results</h3>
          <div class="space-y-2 text-sm mb-4">
            <div class="flex justify-between">
              <span class="text-gray-500">Total rows</span>
              <span class="font-semibold">{{ importResult.total }}</span>
            </div>
            <div class="flex justify-between text-emerald-600">
              <span>✅ Imported</span>
              <span class="font-semibold">{{ importResult.success }}</span>
            </div>
            <div v-if="importResult.failed > 0" class="flex justify-between text-rose-600">
              <span>❌ Failed</span>
              <span class="font-semibold">{{ importResult.failed }}</span>
            </div>
          </div>
          <div v-if="importResult.errors.length" class="mb-4 max-h-32 overflow-y-auto text-xs bg-rose-50 dark:bg-rose-900/20 rounded-lg p-3 space-y-1">
            <p v-for="(e, i) in importResult.errors" :key="i" class="text-rose-600">{{ e }}</p>
          </div>
          <button class="btn-primary w-full justify-center" @click="importResult = null; fetchPage()">Done</button>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Import Progress -->
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="importing" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        <div class="relative card w-full max-w-xs p-6 shadow-2xl text-center">
          <div class="w-10 h-10 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
          <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Importing products…</p>
          <p class="text-xs text-gray-400 mt-1">{{ importProgress.done }} / {{ importProgress.total }}</p>
          <div class="mt-3 h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
            <div class="h-full bg-blue-500 transition-all duration-300 rounded-full"
                 :style="{ width: importProgress.total ? (importProgress.done / importProgress.total * 100) + '%' : '0%' }"></div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import api from '../api/client'
import { PlusIcon, SearchIcon, EditIcon, PackageIcon, ChevronLeftIcon, ChevronRightIcon, DownloadIcon, UploadIcon, FileDownIcon } from 'lucide-vue-next'
import * as XLSX from 'xlsx'

const products = ref<any[]>([])
const search = ref('')
const filterStatus = ref('')
const loading = ref(true)
const meta = ref({ current_page: 1, last_page: 1, from: 1, to: 0, total: 0 })

const importFileRef = ref<HTMLInputElement | null>(null)
const importing = ref(false)
const importProgress = ref({ done: 0, total: 0 })
const importResult = ref<{ total: number; success: number; failed: number; errors: string[] } | null>(null)

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

function triggerImport() {
  importFileRef.value?.click()
}

function downloadSample() {
  const sample = [
    {
      'Name *': 'Sample Product',
      'SKU': 'SKU001',
      'Category': 'General',
      'HSN Code': '9999',
      'GST % (0/0.25/3/5/12/18/28)': 18,
      'Purchase Price': 100,
      'Selling Price *': 150,
      'MRP': 175,
      'Opening Stock': 50,
      'Unit (pcs/kg/ltr/box)': 'pcs',
    },
    {
      'Name *': 'Basmati Rice 5kg',
      'SKU': 'RICE001',
      'Category': 'Grocery',
      'HSN Code': '1006',
      'GST % (0/0.25/3/5/12/18/28)': 5,
      'Purchase Price': 280,
      'Selling Price *': 350,
      'MRP': 400,
      'Opening Stock': 100,
      'Unit (pcs/kg/ltr/box)': 'kg',
    },
  ]
  const ws = XLSX.utils.json_to_sheet(sample)
  ws['!cols'] = Object.keys(sample[0]).map(() => ({ wch: 24 }))
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Products')
  XLSX.writeFile(wb, 'OpenVyapar_Product_Import_Sample.xlsx')
}

async function handleImport(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  ;(event.target as HTMLInputElement).value = ''

  const data = await file.arrayBuffer()
  const wb = XLSX.read(data)
  const ws = wb.Sheets[wb.SheetNames[0]]
  const rows: any[] = XLSX.utils.sheet_to_json(ws)

  if (!rows.length) return

  importing.value = true
  importProgress.value = { done: 0, total: rows.length }

  const result = { total: rows.length, success: 0, failed: 0, errors: [] as string[] }

  for (let i = 0; i < rows.length; i++) {
    const row = rows[i]
    const name = row['Name *'] || row['Name'] || row['name'] || ''
    const sellingPrice = row['Selling Price *'] || row['Selling Price'] || row['selling_price'] || 0

    if (!name) {
      result.failed++
      result.errors.push(`Row ${i + 2}: Name is required`)
      importProgress.value.done++
      continue
    }

    try {
      await api.post('/products', {
        name: String(name).trim(),
        sku: row['SKU'] || row['sku'] || null,
        category_name: row['Category'] || row['category'] || null,
        hsn_code: row['HSN Code'] || row['hsn_code'] || null,
        gst_rate: Number(row['GST % (0/0.25/3/5/12/18/28)'] ?? row['GST%'] ?? row['gst_rate'] ?? 18),
        purchase_price: Number(row['Purchase Price'] || row['purchase_price'] || 0),
        selling_price: Number(sellingPrice),
        mrp: row['MRP'] || row['mrp'] ? Number(row['MRP'] || row['mrp']) : null,
        opening_stock: Number(row['Opening Stock'] || row['opening_stock'] || 0),
        unit: row['Unit (pcs/kg/ltr/box)'] || row['Unit'] || row['unit'] || 'pcs',
        is_active: true,
      })
      result.success++
    } catch (e: any) {
      result.failed++
      const msg = e.response?.data?.message || Object.values(e.response?.data?.errors ?? {}).flat().join(', ') || 'Error'
      result.errors.push(`Row ${i + 2} (${name}): ${msg}`)
    }

    importProgress.value.done++
  }

  importing.value = false
  importResult.value = result
}

onMounted(() => fetchPage())
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: all 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
