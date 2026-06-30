<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
      <div class="card w-full max-w-md p-7 shadow-2xl animate-scale-in">
        <!-- Icon -->
        <div class="flex flex-col items-center mb-5">
          <div class="w-16 h-16 rounded-2xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center mb-3">
            <ShieldAlertIcon class="w-8 h-8 text-amber-600 dark:text-amber-400" />
          </div>
          <h2 class="text-xl font-extrabold text-gray-900 dark:text-white text-center">Set Your Password</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 text-center mt-1">
            You're using the default password. Please set a secure password before continuing.
          </p>
        </div>

        <div class="space-y-3">
          <div>
            <label class="label">Current Password</label>
            <input v-model="form.current_password" type="password" class="input" placeholder="Enter current password" autocomplete="current-password" />
          </div>
          <div>
            <label class="label">New Password</label>
            <input v-model="form.password" type="password" class="input" :class="strengthClass" placeholder="Min. 8 characters" autocomplete="new-password" />
            <!-- Strength bar -->
            <div v-if="form.password" class="mt-1 flex gap-1">
              <div v-for="i in 4" :key="i" class="h-1 flex-1 rounded-full" :class="strength >= i ? strengthBarColor : 'bg-gray-200 dark:bg-gray-700'"></div>
            </div>
            <p v-if="form.password" class="text-xs mt-1" :class="strength >= 3 ? 'text-emerald-600' : 'text-amber-500'">{{ strengthLabel }}</p>
          </div>
          <div>
            <label class="label">Confirm New Password</label>
            <input v-model="form.password_confirmation" type="password" class="input" placeholder="Re-enter new password" autocomplete="new-password" />
            <p v-if="form.password_confirmation && form.password !== form.password_confirmation" class="text-xs text-rose-600 mt-1">Passwords do not match</p>
          </div>

          <div v-if="error" class="text-xs text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-900/20 px-3 py-2 rounded-xl">{{ error }}</div>

          <button @click="submit" :disabled="saving || !canSubmit" class="btn-primary w-full justify-center mt-1">
            {{ saving ? 'Saving…' : 'Set New Password & Continue' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { ShieldAlertIcon } from 'lucide-vue-next'
import api from '../api/client'

const emit = defineEmits<{ done: [] }>()

const form = ref({ current_password: '', password: '', password_confirmation: '' })
const saving = ref(false)
const error  = ref('')

const strength = computed(() => {
  const p = form.value.password
  if (!p) return 0
  let s = 0
  if (p.length >= 8) s++
  if (/[A-Z]/.test(p)) s++
  if (/[0-9]/.test(p)) s++
  if (/[^A-Za-z0-9]/.test(p)) s++
  return s
})

const strengthLabel    = computed(() => ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'][strength.value] ?? '')
const strengthBarColor = computed(() => ['bg-rose-500', 'bg-rose-400', 'bg-amber-400', 'bg-emerald-400', 'bg-emerald-500'][strength.value - 1] ?? 'bg-gray-200')
const strengthClass    = computed(() => '')

const canSubmit = computed(() =>
  form.value.current_password.length >= 1 &&
  form.value.password.length >= 8 &&
  form.value.password === form.value.password_confirmation
)

async function submit() {
  saving.value = true
  error.value  = ''
  try {
    await api.post('/auth/change-password', form.value)
    emit('done')
  } catch (e: any) {
    error.value = e.response?.data?.message ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ') ?? 'Failed'
  } finally {
    saving.value = false
  }
}
</script>
