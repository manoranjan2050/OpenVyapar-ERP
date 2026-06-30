<template>
  <div class="max-w-4xl space-y-6 animate-fade-up">

    <div class="page-header animate-fade-down">
      <div>
        <h1 class="page-title">Users & Roles</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Manage staff accounts and their permissions</p>
      </div>
      <button class="btn-primary" @click="openForm()">
        <PlusIcon class="w-4 h-4" /> Add User
      </button>
    </div>

    <!-- Role legend -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 animate-fade-up">
      <div v-for="r in roleDefs" :key="r.name" class="card p-3 flex items-start gap-3">
        <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0" :class="r.bg">
          <component :is="r.icon" class="w-4 h-4" :class="r.color" />
        </div>
        <div>
          <p class="text-xs font-bold capitalize" :class="r.color">{{ r.name }}</p>
          <p class="text-xs text-gray-400 mt-0.5 leading-snug">{{ r.desc }}</p>
        </div>
      </div>
    </div>

    <!-- Users table -->
    <div class="card overflow-hidden animate-fade-up delay-75">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50">
            <th class="table-header">User</th>
            <th class="table-header">Email</th>
            <th class="table-header text-center">Role</th>
            <th class="table-header text-center">Status</th>
            <th class="table-header text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
          <tr v-if="loading" v-for="n in 3" :key="n">
            <td colspan="5" class="px-4 py-3"><div class="shimmer h-3 w-full rounded-full"></div></td>
          </tr>
          <tr v-else v-for="u in users" :key="u.id" class="table-row group">
            <td class="table-cell">
              <div class="flex items-center gap-3">
                <!-- Avatar with click-to-upload -->
                <div class="relative group/av cursor-pointer" @click="triggerAvatarUpload(u)">
                  <img v-if="u.avatar_url"
                       :src="u.avatar_url"
                       :key="u.avatar_url"
                       class="w-9 h-9 rounded-xl object-cover" />
                  <div v-else
                       class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold"
                       :class="roleColor(u.role)">
                    {{ u.name?.charAt(0)?.toUpperCase() }}
                  </div>
                  <div class="absolute inset-0 rounded-xl bg-black/50 opacity-0 group-hover/av:opacity-100 transition-opacity flex items-center justify-center">
                    <CameraIcon class="w-3.5 h-3.5 text-white" />
                  </div>
                </div>
                <p class="font-semibold text-gray-900 dark:text-white">{{ u.name }}</p>
              </div>
            </td>
            <td class="table-cell text-gray-500 text-xs">{{ u.email }}</td>
            <td class="table-cell text-center">
              <span class="px-2.5 py-1 rounded-lg text-xs font-bold capitalize" :class="roleBadge(u.role)">
                {{ u.role }}
              </span>
            </td>
            <td class="table-cell text-center">
              <span :class="u.is_active ? 'badge-green' : 'badge-gray'">
                {{ u.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="table-cell text-right">
              <div class="flex items-center gap-2 justify-end opacity-0 group-hover:opacity-100 transition-all">
                <button class="text-xs font-semibold text-blue-600 dark:text-blue-400" @click="openForm(u)">Edit</button>
                <button class="text-xs font-semibold text-rose-500 hover:text-rose-700" @click="deleteUser(u)"
                        :disabled="u.id === currentUserId">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Hidden avatar file input -->
    <input ref="avatarInput" type="file" accept="image/*" class="hidden" @change="onAvatarSelected" />

    <!-- Form Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showForm = false"></div>
          <div class="relative card w-full max-w-md p-6 shadow-2xl animate-scale-in">
            <div class="flex items-center justify-between mb-5">
              <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ editUser ? 'Edit User' : 'New User' }}</h3>
              <button @click="showForm = false" class="btn-icon"><XIcon class="w-4 h-4" /></button>
            </div>

            <form @submit.prevent="save" class="space-y-4">
              <div>
                <label class="label">Full Name *</label>
                <input v-model="form.name" class="input" required placeholder="Rajesh Kumar" />
              </div>
              <div>
                <label class="label">Email *</label>
                <input v-model="form.email" type="email" class="input" required
                       placeholder="rajesh@myshop.in" :disabled="!!editUser" />
              </div>
              <div>
                <label class="label">{{ editUser ? 'New Password' : 'Password *' }}</label>
                <div class="relative">
                  <input v-model="form.password"
                         :type="showPassword ? 'text' : 'password'"
                         class="input pr-10"
                         :required="!editUser"
                         :placeholder="editUser ? 'Leave blank to keep current' : 'Min 6 characters'" />
                  <button type="button"
                          class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                          @click="showPassword = !showPassword">
                    <EyeOffIcon v-if="showPassword" class="w-4 h-4" />
                    <EyeIcon v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>
              <div v-if="form.password">
                <label class="label">Confirm Password *</label>
                <div class="relative">
                  <input v-model="form.confirmPassword"
                         :type="showConfirm ? 'text' : 'password'"
                         class="input pr-10"
                         :required="!!form.password"
                         placeholder="Re-enter password" />
                  <button type="button"
                          class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                          @click="showConfirm = !showConfirm">
                    <EyeOffIcon v-if="showConfirm" class="w-4 h-4" />
                    <EyeIcon v-else class="w-4 h-4" />
                  </button>
                </div>
                <p v-if="form.confirmPassword" class="text-xs mt-1"
                   :class="passwordsMatch ? 'text-emerald-600' : 'text-rose-500'">
                  {{ passwordsMatch ? '✓ Passwords match' : '✗ Passwords do not match' }}
                </p>
              </div>
              <div>
                <label class="label">Role *</label>
                <select v-model="form.role" class="input" required>
                  <option value="admin">Admin — Full access</option>
                  <option value="accountant">Accountant — Reports & payments</option>
                  <option value="cashier">Cashier — Invoices only</option>
                  <option value="viewer">Viewer — Read only</option>
                </select>
              </div>
              <div v-if="editUser" class="flex items-center gap-3">
                <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded" id="active" />
                <label for="active" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Account is active</label>
              </div>
              <div v-if="error" class="flex items-center gap-2 text-rose-600 text-sm bg-rose-50 dark:bg-rose-900/20 px-3 py-2 rounded-xl">
                <AlertCircleIcon class="w-4 h-4 flex-shrink-0" /> {{ error }}
              </div>
              <div class="flex gap-2 pt-1">
                <button type="submit" class="btn-primary flex-1 justify-center"
                        :disabled="saving || (!!form.password && !passwordsMatch)">
                  <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                  </svg>
                  {{ saving ? 'Saving…' : editUser ? 'Update User' : 'Create User' }}
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
import { useAuthStore } from '../stores/auth'
import {
  PlusIcon, XIcon, AlertCircleIcon, ShieldIcon,
  CalculatorIcon, CreditCardIcon, EyeIcon, EyeOffIcon, CameraIcon,
} from 'lucide-vue-next'

const users    = ref<any[]>([])
const loading  = ref(true)
const showForm = ref(false)
const saving   = ref(false)
const error    = ref('')
const editUser = ref<any>(null)
const auth     = useAuthStore()

const showPassword   = ref(false)
const showConfirm    = ref(false)
const avatarInput    = ref<HTMLInputElement>()
const uploadingUserId = ref<number | null>(null)

const currentUserId = computed(() => auth.user?.id)

const form = ref({
  name: '', email: '', password: '', confirmPassword: '',
  role: 'cashier', is_active: true
})

const passwordsMatch = computed(() =>
  form.value.password === form.value.confirmPassword
)

const roleDefs = [
  { name: 'admin',      bg: 'bg-blue-100 dark:bg-blue-900/30',     color: 'text-blue-600 dark:text-blue-400',     icon: ShieldIcon,     desc: 'Full access to everything' },
  { name: 'accountant', bg: 'bg-violet-100 dark:bg-violet-900/30', color: 'text-violet-600 dark:text-violet-400', icon: CalculatorIcon, desc: 'View reports, record payments' },
  { name: 'cashier',    bg: 'bg-emerald-100 dark:bg-emerald-900/30',color: 'text-emerald-600 dark:text-emerald-400',icon: CreditCardIcon, desc: 'Create invoices only' },
  { name: 'viewer',     bg: 'bg-gray-100 dark:bg-gray-800',         color: 'text-gray-500 dark:text-gray-400',    icon: EyeIcon,        desc: 'Read-only access' },
]

const roleColor = (r: string) => ({
  admin: 'bg-blue-600', accountant: 'bg-violet-600',
  cashier: 'bg-emerald-600', viewer: 'bg-gray-400'
}[r] ?? 'bg-gray-400')

const roleBadge = (r: string) => ({
  admin:      'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  accountant: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
  cashier:    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
  viewer:     'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
}[r] ?? 'bg-gray-100 text-gray-600')

function triggerAvatarUpload(u: any) {
  uploadingUserId.value = u.id
  avatarInput.value?.click()
}

async function onAvatarSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file || !uploadingUserId.value) return
  const fd = new FormData()
  fd.append('avatar', file)
  try {
    const { data } = await api.post(`/users/${uploadingUserId.value}/avatar`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    const u = users.value.find(x => x.id === uploadingUserId.value)
    if (u) u.avatar_url = data.avatar_url + '?t=' + Date.now()
  } catch {
    alert('Avatar upload failed.')
  } finally {
    if (avatarInput.value) avatarInput.value.value = ''
    uploadingUserId.value = null
  }
}

function openForm(u?: any) {
  editUser.value     = u ?? null
  error.value        = ''
  showPassword.value = false
  showConfirm.value  = false
  form.value = u
    ? { name: u.name, email: u.email, password: '', confirmPassword: '', role: u.role, is_active: u.is_active }
    : { name: '', email: '', password: '', confirmPassword: '', role: 'cashier', is_active: true }
  showForm.value = true
}

async function save() {
  if (form.value.password && !passwordsMatch.value) {
    error.value = 'Passwords do not match.'
    return
  }
  saving.value = true
  error.value  = ''
  try {
    const payload: any = {
      name: form.value.name, email: form.value.email,
      role: form.value.role, is_active: form.value.is_active,
    }
    if (form.value.password) payload.password = form.value.password
    if (editUser.value) {
      await api.put(`/users/${editUser.value.id}`, payload)
    } else {
      await api.post('/users', payload)
    }
    showForm.value = false
    await loadUsers()
  } catch (e: any) {
    error.value = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ')
      ?? 'Error saving user.'
  } finally {
    saving.value = false
  }
}

async function deleteUser(u: any) {
  if (!confirm(`Delete user ${u.name}?`)) return
  await api.delete(`/users/${u.id}`)
  await loadUsers()
}

async function loadUsers() {
  loading.value = true
  const { data } = await api.get('/users')
  users.value   = data
  loading.value = false
}

onMounted(() => loadUsers())
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
