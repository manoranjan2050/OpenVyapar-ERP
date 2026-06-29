<template>
  <div class="space-y-6 animate-fade-up">

    <!-- Stats row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="card p-4 text-center">
        <p class="text-2xl font-extrabold text-blue-600 dark:text-blue-400">{{ stats.total ?? 0 }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Total Logs</p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ stats.today ?? 0 }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Today</p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-2xl font-extrabold text-violet-600 dark:text-violet-400">{{ stats.week ?? 0 }}</p>
        <p class="text-xs text-gray-500 mt-0.5">This Week</p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-2xl font-extrabold text-amber-600 dark:text-amber-400">{{ Object.keys(stats.by_event ?? {}).length }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Event Types</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="card p-4">
      <div class="flex flex-wrap gap-3">
        <input v-model="filters.search" @input="debounceLoad" type="text" placeholder="Search logs…"
               class="form-input flex-1 min-w-40 text-sm" />
        <select v-model="filters.event" @change="load" class="form-input w-40 text-sm">
          <option value="">All Events</option>
          <option v-for="e in eventTypes" :key="e" :value="e">{{ e }}</option>
        </select>
        <select v-model="filters.model" @change="load" class="form-input w-40 text-sm">
          <option value="">All Models</option>
          <option v-for="m in modelTypes" :key="m" :value="m">{{ m }}</option>
        </select>
        <input v-model="filters.from" @change="load" type="date" class="form-input w-36 text-sm" />
        <input v-model="filters.to" @change="load" type="date" class="form-input w-36 text-sm" />
        <button @click="clearFilters" class="btn-secondary text-sm">Clear</button>
        <button @click="load" class="btn-primary text-sm flex items-center gap-1.5">
          <RefreshCwIcon class="w-4 h-4" /> Refresh
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-400">Loading logs…</div>
      <div v-else-if="!logs.length" class="p-8 text-center text-gray-400">No logs found.</div>
      <div v-else>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400 w-32">Time</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400 w-24">Event</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400 w-28">Model</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Description</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400 w-32">User</th>
              <th class="px-4 py-3 w-10"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs" :key="log.id"
                class="border-t border-gray-50 dark:border-gray-800/50 hover:bg-gray-50 dark:hover:bg-gray-800/30 cursor-pointer"
                @click="selected = log">
              <td class="px-4 py-2.5 text-gray-500 text-xs font-mono whitespace-nowrap">
                {{ formatTime(log.created_at) }}
              </td>
              <td class="px-4 py-2.5">
                <span :class="eventBadge(log.event)" class="px-2 py-0.5 rounded-full text-xs font-bold">
                  {{ log.event || 'log' }}
                </span>
              </td>
              <td class="px-4 py-2.5 text-gray-500 text-xs">{{ shortModel(log.subject_type) }}</td>
              <td class="px-4 py-2.5 text-gray-800 dark:text-gray-200 truncate max-w-xs">{{ log.description }}</td>
              <td class="px-4 py-2.5 text-gray-500 text-xs truncate">{{ log.causer?.name ?? '—' }}</td>
              <td class="px-4 py-2.5">
                <EyeIcon class="w-4 h-4 text-gray-300 hover:text-blue-500 transition-colors" />
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/30">
          <p class="text-xs text-gray-500">{{ total }} total logs</p>
          <div class="flex gap-1">
            <button @click="page--; load()" :disabled="page <= 1" class="btn-secondary text-xs px-2.5 py-1">Prev</button>
            <span class="px-3 py-1 text-xs text-gray-600 dark:text-gray-400">{{ page }} / {{ pages }}</span>
            <button @click="page++; load()" :disabled="page >= pages" class="btn-secondary text-xs px-2.5 py-1">Next</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Detail modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="selected = null">
          <div class="card w-full max-w-lg p-6 space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="font-bold text-gray-900 dark:text-white">Log Detail #{{ selected.id }}</h3>
              <button @click="selected = null" class="text-gray-400 hover:text-gray-700">✕</button>
            </div>
            <div class="space-y-3 text-sm">
              <div class="grid grid-cols-2 gap-3">
                <div><p class="text-gray-400 text-xs mb-1">Event</p><span :class="eventBadge(selected.event)" class="px-2 py-0.5 rounded-full text-xs font-bold">{{ selected.event }}</span></div>
                <div><p class="text-gray-400 text-xs mb-1">Time</p><p class="font-mono text-xs">{{ selected.created_at }}</p></div>
                <div><p class="text-gray-400 text-xs mb-1">Model</p><p>{{ shortModel(selected.subject_type) }} #{{ selected.subject_id }}</p></div>
                <div><p class="text-gray-400 text-xs mb-1">User</p><p>{{ selected.causer?.name ?? '—' }}</p></div>
              </div>
              <div><p class="text-gray-400 text-xs mb-1">Description</p><p class="text-gray-800 dark:text-gray-200">{{ selected.description }}</p></div>
              <div v-if="selected.properties && Object.keys(selected.properties).length">
                <p class="text-gray-400 text-xs mb-1">Properties</p>
                <pre class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-xs overflow-auto max-h-48 text-gray-700 dark:text-gray-300">{{ JSON.stringify(selected.properties, null, 2) }}</pre>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../api/client'
import { RefreshCwIcon, EyeIcon } from 'lucide-vue-next'

const logs     = ref<any[]>([])
const stats    = ref<any>({})
const selected = ref<any>(null)
const loading  = ref(false)
const total    = ref(0)
const pages    = ref(1)
const page     = ref(1)
const filters  = ref({ search: '', event: '', model: '', from: '', to: '' })

const eventTypes = ['created', 'updated', 'deleted', 'restored', 'login', 'logout']
const modelTypes = ['SalesInvoice', 'PurchaseInvoice', 'Product', 'Customer', 'Supplier', 'Payment', 'User']

let debounceTimer: any

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/activity-logs', { params: { ...filters.value, page: page.value, per_page: 30 } })
    logs.value  = data.data ?? []
    total.value = data.total ?? 0
    pages.value = data.pages ?? 1
  } finally { loading.value = false }
}

async function loadStats() {
  const { data } = await api.get('/activity-logs/stats')
  stats.value = data
}

function debounceLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; load() }, 400)
}

function clearFilters() {
  filters.value = { search: '', event: '', model: '', from: '', to: '' }
  page.value = 1; load()
}

function formatTime(dt: string) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

function shortModel(type: string) {
  if (!type) return '—'
  return type.split('\\').pop() ?? type
}

function eventBadge(event: string) {
  const map: Record<string, string> = {
    created:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    updated:  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    deleted:  'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
    restored: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
    login:    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
  }
  return map[event] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
}

onMounted(() => { load(); loadStats() })
</script>
