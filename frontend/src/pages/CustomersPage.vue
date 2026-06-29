<template>
  <div class="space-y-5">

    <!-- Header -->
    <div class="page-header animate-fade-down">
      <div>
        <h1 class="page-title">Customers</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ customers.length }} customers registered</p>
      </div>
      <button class="btn-primary" @click="openForm()">
        <PlusIcon class="w-4 h-4" /> New Customer
      </button>
    </div>

    <!-- Search -->
    <div class="flex gap-3 animate-fade-up">
      <div class="relative flex-1 max-w-sm">
        <SearchIcon class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
        <input v-model="search" type="text" placeholder="Search customers…" class="input pl-10" @input="debouncedFetch" />
      </div>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden animate-fade-up delay-75">
      <table class="w-full">
        <thead>
          <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
            <th class="table-header">Customer</th>
            <th class="table-header">Phone</th>
            <th class="table-header">GSTIN</th>
            <th class="table-header">City / State</th>
            <th class="table-header text-right">Credit Limit</th>
            <th class="table-header text-center">Status</th>
            <th class="table-header w-16"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
          <tr v-for="(c, i) in customers" :key="c.id" class="table-row group">
            <td class="table-cell">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                     :class="avatarColor(i)">
                  {{ c.name.charAt(0).toUpperCase() }}
                </div>
                <div>
                  <p class="font-semibold text-gray-900 dark:text-white">{{ c.name }}</p>
                  <p class="text-xs text-gray-400">{{ c.email ?? '–' }}</p>
                </div>
              </div>
            </td>
            <td class="table-cell text-gray-600 dark:text-gray-400">{{ c.phone ?? '–' }}</td>
            <td class="table-cell font-mono text-xs text-gray-500">{{ c.gstin ?? '–' }}</td>
            <td class="table-cell text-gray-600 dark:text-gray-400">
              {{ [c.billing_city, c.billing_state].filter(Boolean).join(', ') || '–' }}
            </td>
            <td class="table-cell text-right font-semibold text-gray-700 dark:text-gray-300">
              {{ c.credit_limit > 0 ? '₹' + Number(c.credit_limit).toLocaleString('en-IN') : '–' }}
            </td>
            <td class="table-cell text-center">
              <span :class="c.is_active ? 'badge-green' : 'badge-gray'">
                <span class="w-1.5 h-1.5 rounded-full inline-block mr-1"
                      :class="c.is_active ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                {{ c.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="table-cell text-right">
              <button @click="openForm(c)"
                class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 dark:text-blue-400
                       hover:text-blue-700 opacity-0 group-hover:opacity-100 transition-all">
                <EditIcon class="w-3.5 h-3.5" /> Edit
              </button>
            </td>
          </tr>
          <tr v-if="!customers.length">
            <td colspan="7" class="py-16 text-center">
              <div class="flex flex-col items-center gap-3">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                  <UsersIcon class="w-7 h-7 text-gray-400" />
                </div>
                <p class="text-sm font-semibold text-gray-500">No customers found</p>
                <button class="btn-primary mt-1" @click="openForm()">
                  <PlusIcon class="w-4 h-4" /> Add Customer
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showForm = false"></div>
          <div class="relative card w-full max-w-lg p-6 shadow-2xl animate-scale-in">
            <div class="flex items-center justify-between mb-5">
              <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                  {{ editId ? 'Edit Customer' : 'New Customer' }}
                </h3>
                <p class="text-sm text-gray-400 mt-0.5">Fill in customer details</p>
              </div>
              <button @click="showForm = false" class="btn-icon">
                <XIcon class="w-4 h-4" />
              </button>
            </div>
            <form @submit.prevent="saveCustomer" class="space-y-3">
              <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                  <label class="label">Name *</label>
                  <input v-model="form.name" class="input" required placeholder="Customer name" />
                </div>
                <div>
                  <label class="label">Phone</label>
                  <input v-model="form.phone" class="input" placeholder="+91 98765 43210" />
                </div>
                <div>
                  <label class="label">Email</label>
                  <input v-model="form.email" type="email" class="input" placeholder="email@example.com" />
                </div>
                <div>
                  <label class="label">GSTIN</label>
                  <input v-model="form.gstin" class="input font-mono" maxlength="15" placeholder="22AAAAA0000A1Z5" />
                </div>
                <div>
                  <label class="label">Credit Limit ₹</label>
                  <input v-model="form.credit_limit" type="number" min="0" class="input" placeholder="0" />
                </div>
                <div>
                  <label class="label">City</label>
                  <input v-model="form.billing_city" class="input" placeholder="Mumbai" />
                </div>
                <div>
                  <label class="label">State</label>
                  <input v-model="form.billing_state" class="input" placeholder="Maharashtra" />
                </div>
                <div class="col-span-2">
                  <label class="label">Billing Address</label>
                  <textarea v-model="form.billing_address" class="input resize-none" rows="2" placeholder="Full billing address" />
                </div>
              </div>
              <div v-if="formError" class="flex items-center gap-2 text-red-600 dark:text-red-400 text-sm bg-red-50 dark:bg-red-900/20 px-3 py-2 rounded-xl">
                <AlertCircleIcon class="w-4 h-4" /> {{ formError }}
              </div>
              <div class="flex gap-2 pt-1">
                <button type="submit" class="btn-primary flex-1 justify-center" :disabled="saving">
                  <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                  </svg>
                  {{ saving ? 'Saving…' : editId ? 'Update Customer' : 'Save Customer' }}
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
import { useDebounceFn } from '@vueuse/core'
import api from '../api/client'
import { PlusIcon, SearchIcon, EditIcon, UsersIcon, XIcon, AlertCircleIcon } from 'lucide-vue-next'

const customers = ref<any[]>([])
const search = ref('')
const showForm = ref(false)
const saving = ref(false)
const formError = ref('')
const editId = ref<number | null>(null)

const emptyForm = () => ({ name: '', phone: '', email: '', gstin: '', credit_limit: 0, billing_city: '', billing_state: '', billing_address: '' })
const form = ref(emptyForm())

const colors = ['bg-blue-500','bg-emerald-500','bg-violet-500','bg-orange-500','bg-rose-500','bg-teal-500']
const avatarColor = (i: number) => colors[i % colors.length]

function openForm(c?: any) {
  editId.value = c?.id ?? null
  form.value = c ? { ...c } : emptyForm()
  formError.value = ''
  showForm.value = true
}

async function loadCustomers() {
  const { data } = await api.get('/customers', { params: { search: search.value } })
  customers.value = data.data
}

const debouncedFetch = useDebounceFn(() => loadCustomers(), 300)

async function saveCustomer() {
  saving.value = true
  formError.value = ''
  try {
    if (editId.value) {
      await api.put(`/customers/${editId.value}`, form.value)
    } else {
      await api.post('/customers', form.value)
    }
    showForm.value = false
    await loadCustomers()
  } catch (e: any) {
    formError.value = e.response?.data?.message ?? 'Error saving.'
  } finally {
    saving.value = false
  }
}

onMounted(() => loadCustomers())
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .card, .modal-leave-to .card { transform: scale(0.95) translateY(16px); }
</style>
