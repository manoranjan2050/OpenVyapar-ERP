<template>
  <div class="max-w-5xl space-y-6 animate-fade-up">

    <div class="page-header animate-fade-down">
      <div>
        <h1 class="page-title">Delivery Challans</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Issue goods before billing — convert to invoice later</p>
      </div>
      <button class="btn-primary" @click="openForm()">
        <PlusIcon class="w-4 h-4" /> New Challan
      </button>
    </div>

    <!-- Status filter chips -->
    <div class="flex gap-2 flex-wrap">
      <button v-for="s in statuses" :key="s.value"
              class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all"
              :class="filter === s.value
                ? 'bg-blue-600 text-white shadow-sm shadow-blue-200 dark:shadow-none'
                : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'"
              @click="filter = s.value">
        {{ s.label }}
        <span class="ml-1 opacity-70">{{ statusCount(s.value) }}</span>
      </button>
    </div>

    <!-- Challans list -->
    <div class="card overflow-hidden animate-fade-up delay-75">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
            <th class="table-header">Challan No</th>
            <th class="table-header">Date</th>
            <th class="table-header">Customer</th>
            <th class="table-header text-right">Items</th>
            <th class="table-header text-center">Status</th>
            <th class="table-header text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
          <tr v-if="loading" v-for="n in 4" :key="n">
            <td colspan="6" class="px-4 py-3"><div class="shimmer h-3 w-full rounded-full"></div></td>
          </tr>
          <tr v-else-if="filtered.length === 0">
            <td colspan="6" class="py-16 text-center">
              <TruckIcon class="w-10 h-10 mx-auto text-gray-200 dark:text-gray-700 mb-3" />
              <p class="text-gray-400 text-sm">No challans yet</p>
              <button class="btn-primary mt-4 mx-auto" @click="openForm()">
                <PlusIcon class="w-4 h-4" /> Create First Challan
              </button>
            </td>
          </tr>
          <tr v-else v-for="c in filtered" :key="c.id" class="table-row group cursor-pointer" @click="openDetail(c)">
            <td class="table-cell font-mono font-semibold text-blue-600 dark:text-blue-400">{{ c.challan_number }}</td>
            <td class="table-cell text-gray-500 text-xs">{{ c.challan_date }}</td>
            <td class="table-cell">
              <p class="font-medium text-gray-800 dark:text-gray-200">{{ c.customer?.name ?? '—' }}</p>
              <p v-if="c.customer?.phone" class="text-xs text-gray-400">{{ c.customer.phone }}</p>
            </td>
            <td class="table-cell text-right text-gray-600 dark:text-gray-400">
              {{ c.items?.length ?? 0 }} item{{ c.items?.length === 1 ? '' : 's' }}
            </td>
            <td class="table-cell text-center">
              <span class="px-2.5 py-1 rounded-lg text-xs font-bold capitalize" :class="statusBadge(c.status)">
                {{ c.status }}
              </span>
            </td>
            <td class="table-cell text-right">
              <div class="flex items-center gap-1.5 justify-end opacity-0 group-hover:opacity-100 transition-all" @click.stop>
                <button v-if="c.status === 'draft'"
                        class="text-xs font-semibold text-emerald-600 hover:text-emerald-700"
                        @click="dispatch(c)">Dispatch</button>
                <button v-if="c.status !== 'converted' && c.status !== 'cancelled'"
                        class="text-xs font-semibold text-violet-600 hover:text-violet-700"
                        @click="convert(c)">→ Invoice</button>
                <button v-if="c.status !== 'cancelled'"
                        class="text-xs font-semibold text-rose-500 hover:text-rose-700"
                        @click="cancel(c)">Cancel</button>
                <button class="text-xs font-semibold text-gray-500 hover:text-gray-700"
                        @click="printChallan(c)">Print</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Detail / Print panel -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="detail = null"></div>
          <div class="relative card w-full max-w-2xl p-0 shadow-2xl overflow-hidden animate-scale-in">
            <!-- Print area -->
            <div id="challan-print" class="p-8">
              <div class="flex items-start justify-between mb-6">
                <div>
                  <h2 class="text-xl font-extrabold text-gray-900 dark:text-white">Delivery Challan</h2>
                  <p class="text-xs text-gray-500 mt-0.5">{{ detail.challan_number }}</p>
                </div>
                <div class="text-right text-xs text-gray-500 space-y-0.5">
                  <p>Date: <strong>{{ detail.challan_date }}</strong></p>
                  <p v-if="detail.vehicle_no">Vehicle: <strong>{{ detail.vehicle_no }}</strong></p>
                  <p v-if="detail.transporter">Transporter: <strong>{{ detail.transporter }}</strong></p>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                  <p class="text-xs text-gray-400 font-bold uppercase mb-1">Consignee</p>
                  <p class="font-bold">{{ detail.customer?.name ?? '—' }}</p>
                  <p v-if="detail.customer?.phone" class="text-xs text-gray-500">{{ detail.customer.phone }}</p>
                </div>
                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
                  <span class="px-3 py-1 rounded-lg text-xs font-bold capitalize" :class="statusBadge(detail.status)">
                    {{ detail.status }}
                  </span>
                </div>
              </div>
              <table class="w-full text-sm mb-6">
                <thead>
                  <tr class="border-b-2 border-gray-200 dark:border-gray-700">
                    <th class="text-left py-2 text-xs font-bold text-gray-500 uppercase">#</th>
                    <th class="text-left py-2 text-xs font-bold text-gray-500 uppercase">Description</th>
                    <th class="text-center py-2 text-xs font-bold text-gray-500 uppercase">Qty</th>
                    <th class="text-center py-2 text-xs font-bold text-gray-500 uppercase">Unit</th>
                    <th class="text-right py-2 text-xs font-bold text-gray-500 uppercase">Rate</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                  <tr v-for="(item, i) in detail.items" :key="i">
                    <td class="py-2 text-gray-400 text-xs">{{ i + 1 }}</td>
                    <td class="py-2 font-medium">{{ item.name }}</td>
                    <td class="py-2 text-center">{{ item.qty }}</td>
                    <td class="py-2 text-center text-gray-500 text-xs">{{ item.unit ?? '—' }}</td>
                    <td class="py-2 text-right text-gray-600">{{ item.rate ? '₹' + Number(item.rate).toLocaleString('en-IN') : '—' }}</td>
                  </tr>
                </tbody>
              </table>
              <div v-if="detail.notes" class="text-xs text-gray-500 border-t border-gray-100 dark:border-gray-800 pt-3">
                <span class="font-bold">Note:</span> {{ detail.notes }}
              </div>
              <div class="mt-8 grid grid-cols-2 gap-8 text-xs text-gray-400">
                <div class="border-t border-gray-300 dark:border-gray-700 pt-2 text-center">Authorised Signatory</div>
                <div class="border-t border-gray-300 dark:border-gray-700 pt-2 text-center">Receiver's Signature</div>
              </div>
            </div>
            <div class="flex items-center gap-2 px-6 py-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 print:hidden">
              <button class="btn-secondary" @click="window.print()"><PrinterIcon class="w-4 h-4" /> Print</button>
              <button v-if="detail.status === 'draft'" class="btn-primary" @click="dispatch(detail); detail = null">Dispatch</button>
              <button v-if="detail.status !== 'converted' && detail.status !== 'cancelled'"
                      class="btn-secondary" @click="convert(detail); detail = null">→ Convert to Invoice</button>
              <button class="btn-icon ml-auto" @click="detail = null"><XIcon class="w-4 h-4" /></button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- New Challan Form Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showForm = false"></div>
          <div class="relative card w-full max-w-xl shadow-2xl animate-scale-in overflow-y-auto max-h-[90vh]">
            <div class="flex items-center justify-between p-6 pb-4 border-b border-gray-100 dark:border-gray-800">
              <h3 class="text-lg font-bold text-gray-900 dark:text-white">New Delivery Challan</h3>
              <button @click="showForm = false" class="btn-icon"><XIcon class="w-4 h-4" /></button>
            </div>
            <form @submit.prevent="saveForm" class="p-6 space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="label">Date *</label>
                  <input v-model="form.challan_date" type="date" class="input" required />
                </div>
                <div>
                  <label class="label">Customer</label>
                  <select v-model="form.customer_id" class="input">
                    <option value="">— Walk-in / No customer —</option>
                    <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="label">Transporter</label>
                  <input v-model="form.transporter" class="input" placeholder="Shree Ganesh Transport" />
                </div>
                <div>
                  <label class="label">Vehicle No</label>
                  <input v-model="form.vehicle_no" class="input" placeholder="MH 12 AB 1234" />
                </div>
              </div>

              <!-- Items -->
              <div>
                <div class="flex items-center justify-between mb-2">
                  <label class="label mb-0">Items *</label>
                  <button type="button" class="btn-secondary text-xs py-1 px-2.5" @click="addItem">
                    <PlusIcon class="w-3 h-3" /> Add Row
                  </button>
                </div>
                <div class="space-y-2">
                  <div v-for="(item, i) in form.items" :key="i"
                       class="grid grid-cols-12 gap-2 items-center p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50">
                    <input v-model="item.name" class="input col-span-4 text-sm py-1.5" placeholder="Item name" required />
                    <input v-model.number="item.qty" type="number" min="0.01" step="0.01"
                           class="input col-span-2 text-sm py-1.5 text-center" placeholder="Qty" required />
                    <input v-model="item.unit" class="input col-span-2 text-sm py-1.5 text-center" placeholder="Unit" />
                    <input v-model.number="item.rate" type="number" min="0" step="0.01"
                           class="input col-span-3 text-sm py-1.5 text-right" placeholder="Rate ₹" />
                    <button type="button" class="col-span-1 text-gray-300 hover:text-rose-500 flex items-center justify-center"
                            @click="form.items.splice(i, 1)" :disabled="form.items.length === 1">
                      <Trash2Icon class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              </div>

              <div>
                <label class="label">Notes</label>
                <textarea v-model="form.notes" class="input resize-none" rows="2" placeholder="Remarks, instructions, etc." />
              </div>

              <div v-if="formError" class="text-xs text-rose-600 bg-rose-50 dark:bg-rose-900/20 px-3 py-2 rounded-xl">
                {{ formError }}
              </div>
              <div class="flex gap-2 pt-1">
                <button type="submit" class="btn-primary flex-1 justify-center" :disabled="formSaving">
                  <svg v-if="formSaving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                  </svg>
                  {{ formSaving ? 'Creating…' : 'Create Challan' }}
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
import { ref, computed, onMounted } from 'vue'
import api from '../api/client'
import { PlusIcon, XIcon, TruckIcon, PrinterIcon, Trash2Icon } from 'lucide-vue-next'

const challans  = ref<any[]>([])
const customers = ref<any[]>([])
const loading   = ref(true)
const filter    = ref('all')
const detail    = ref<any>(null)
const showForm  = ref(false)
const formSaving = ref(false)
const formError  = ref('')
const window    = globalThis as any

const statuses = [
  { value: 'all',        label: 'All' },
  { value: 'draft',      label: 'Draft' },
  { value: 'dispatched', label: 'Dispatched' },
  { value: 'converted',  label: 'Converted' },
  { value: 'cancelled',  label: 'Cancelled' },
]

const filtered = computed(() =>
  filter.value === 'all' ? challans.value : challans.value.filter(c => c.status === filter.value)
)

const statusCount = (s: string) =>
  s === 'all' ? challans.value.length : challans.value.filter(c => c.status === s).length

const statusBadge = (s: string) => ({
  draft:      'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
  dispatched: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  converted:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
  cancelled:  'bg-rose-100 text-rose-600 dark:bg-rose-900/20 dark:text-rose-400',
}[s] ?? 'bg-gray-100 text-gray-600')

const blankForm = () => ({
  challan_date: new Date().toISOString().split('T')[0],
  customer_id: '',
  transporter: '',
  vehicle_no: '',
  notes: '',
  items: [{ name: '', qty: 1, unit: '', rate: '' }],
})
const form = ref(blankForm())

function openForm() {
  form.value = blankForm()
  formError.value = ''
  showForm.value  = true
}

function addItem() {
  form.value.items.push({ name: '', qty: 1, unit: '', rate: '' })
}

function openDetail(c: any) {
  detail.value = c
}

function printChallan(c: any) {
  detail.value = c
  setTimeout(() => window.print(), 300)
}

async function saveForm() {
  formSaving.value = true
  formError.value  = ''
  try {
    const payload = {
      ...form.value,
      customer_id: form.value.customer_id || null,
    }
    const { data } = await api.post('/challans', payload)
    challans.value.unshift(data)
    showForm.value = false
  } catch (e: any) {
    formError.value = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ')
      ?? 'Error creating challan.'
  } finally {
    formSaving.value = false
  }
}

async function dispatch(c: any) {
  if (!confirm(`Dispatch challan ${c.challan_number}?`)) return
  const { data } = await api.post(`/challans/${c.id}/dispatch`)
  Object.assign(c, data)
}

async function convert(c: any) {
  if (!confirm(`Mark challan ${c.challan_number} as converted to invoice?`)) return
  const { data } = await api.post(`/challans/${c.id}/convert`)
  c.status = 'converted'
}

async function cancel(c: any) {
  if (!confirm(`Cancel challan ${c.challan_number}?`)) return
  const { data } = await api.post(`/challans/${c.id}/cancel`)
  Object.assign(c, data)
}

async function load() {
  loading.value = true
  const [ch, cu] = await Promise.all([
    api.get('/challans'),
    api.get('/customers'),
  ])
  challans.value  = ch.data
  customers.value = cu.data
  loading.value   = false
}

onMounted(() => load())
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
@media print {
  body > * { display: none !important; }
  #challan-print { display: block !important; }
}
</style>
