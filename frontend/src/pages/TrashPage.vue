<template>
  <div class="space-y-6 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-500 to-rose-700 flex items-center justify-center">
          <Trash2Icon class="w-5 h-5 text-white" />
        </div>
        <div>
          <h1 class="text-xl font-bold text-gray-900 dark:text-white">Recycle Bin</h1>
          <p class="text-sm text-gray-500">{{ summary.total ?? 0 }} items — restore or permanently delete</p>
        </div>
      </div>
      <div class="flex gap-2">
        <button @click="restoreAll" v-if="items.length" class="btn-secondary text-sm flex items-center gap-2">
          <RotateCcwIcon class="w-4 h-4" /> Restore All
        </button>
        <button @click="confirmEmpty = true" v-if="items.length"
                class="btn-danger text-sm flex items-center gap-2">
          <Trash2Icon class="w-4 h-4" /> Empty Trash
        </button>
      </div>
    </div>

    <!-- By-type summary chips -->
    <div class="flex flex-wrap gap-2">
      <button @click="typeFilter = ''" :class="!typeFilter ? 'bg-gray-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'"
              class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all">
        All ({{ summary.total ?? 0 }})
      </button>
      <button v-for="(count, key) in summary.by_type" :key="key"
              @click="typeFilter = String(key)"
              :class="typeFilter === String(key) ? 'bg-rose-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'"
              class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all capitalize">
        {{ key }} ({{ count }})
      </button>
    </div>

    <!-- Flash -->
    <div v-if="flash" class="p-3 rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-700 text-sm font-medium">
      {{ flash }}
    </div>

    <!-- Table -->
    <div class="card overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-400">Loading…</div>
      <div v-else-if="!filtered.length" class="p-12 text-center">
        <Trash2Icon class="w-12 h-12 text-gray-200 dark:text-gray-700 mx-auto mb-3" />
        <p class="font-semibold text-gray-400">Trash is empty</p>
        <p class="text-sm text-gray-300 dark:text-gray-600 mt-1">Deleted items will appear here and can be restored.</p>
      </div>
      <div v-else>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400 w-28">Type</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Name / Reference</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400 w-36">Deleted At</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-400 w-32">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in filtered" :key="item._type + item.id"
                class="border-t border-gray-50 dark:border-gray-800/50 hover:bg-gray-50 dark:hover:bg-gray-800/20">
              <td class="px-4 py-3">
                <span :class="typeBadge(item._type)" class="px-2 py-0.5 rounded-full text-xs font-bold capitalize">
                  {{ item._type }}
                </span>
              </td>
              <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">
                {{ item._label }}
                <span v-if="item.total_amount" class="ml-2 text-xs text-gray-400">₹{{ item.total_amount }}</span>
              </td>
              <td class="px-4 py-3 text-gray-500 text-xs font-mono">
                {{ item.deleted_at ? String(item.deleted_at).slice(0, 16).replace('T', ' ') : '—' }}
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button @click="restore(item)" class="text-xs px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 font-semibold transition-all">
                    Restore
                  </button>
                  <button @click="confirmDel = item" class="text-xs px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 font-semibold transition-all">
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Confirm permanent delete -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="confirmDel" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="confirmDel = null">
          <div class="card max-w-sm w-full p-6 space-y-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-900/30 flex items-center justify-center mx-auto">
              <Trash2Icon class="w-6 h-6 text-rose-600 dark:text-rose-400" />
            </div>
            <div class="text-center">
              <h3 class="font-bold text-gray-900 dark:text-white">Permanently Delete?</h3>
              <p class="text-sm text-gray-500 mt-1"><strong>{{ confirmDel._label }}</strong> will be deleted forever. This cannot be undone.</p>
            </div>
            <div class="flex gap-2">
              <button @click="forceDelete(confirmDel); confirmDel = null" class="btn-danger flex-1 text-sm">Delete Forever</button>
              <button @click="confirmDel = null" class="btn-secondary flex-1 text-sm">Cancel</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Confirm empty trash -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="confirmEmpty" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="confirmEmpty = false">
          <div class="card max-w-sm w-full p-6 space-y-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-900/30 flex items-center justify-center mx-auto">
              <Trash2Icon class="w-6 h-6 text-rose-600 dark:text-rose-400" />
            </div>
            <div class="text-center">
              <h3 class="font-bold text-gray-900 dark:text-white">Empty Entire Trash?</h3>
              <p class="text-sm text-gray-500 mt-1">All <strong>{{ summary.total }}</strong> items will be permanently deleted. This CANNOT be undone.</p>
            </div>
            <div class="flex gap-2">
              <button @click="emptyTrash" class="btn-danger flex-1 text-sm">Empty Trash</button>
              <button @click="confirmEmpty = false" class="btn-secondary flex-1 text-sm">Cancel</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '../api/client'
import { Trash2Icon, RotateCcwIcon } from 'lucide-vue-next'

const items       = ref<any[]>([])
const summary     = ref<any>({})
const loading     = ref(false)
const flash       = ref('')
const typeFilter  = ref('')
const confirmDel  = ref<any>(null)
const confirmEmpty = ref(false)

const filtered = computed(() =>
  typeFilter.value ? items.value.filter(i => i._type === typeFilter.value) : items.value
)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/trash')
    items.value   = data.items ?? []
    summary.value = { total: data.total, by_type: data.by_type }
  } finally { loading.value = false }
}

function showFlash(msg: string) {
  flash.value = msg
  setTimeout(() => flash.value = '', 3000)
}

async function restore(item: any) {
  await api.post('/trash/restore', { type: item._type, id: item.id })
  showFlash(`${item._label} restored successfully!`)
  load()
}

async function forceDelete(item: any) {
  await api.delete('/trash/item', { data: { type: item._type, id: item.id } })
  showFlash(`${item._label} permanently deleted.`)
  load()
}

async function restoreAll() {
  await api.post('/trash/restore-all')
  showFlash('All items restored!')
  load()
}

async function emptyTrash() {
  confirmEmpty.value = false
  await api.delete('/trash/empty')
  showFlash('Trash emptied.')
  load()
}

function typeBadge(type: string) {
  const map: Record<string, string> = {
    sales:     'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    purchases: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
    products:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    customers: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    suppliers: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    payments:  'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
    users:     'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
  }
  return map[type] ?? 'bg-gray-100 text-gray-600'
}

onMounted(load)
</script>
