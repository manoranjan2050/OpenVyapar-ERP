<template>
  <div class="min-h-screen relative overflow-hidden flex items-center justify-center p-4">

    <!-- Animated gradient background -->
    <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 animate-gradient"></div>

    <!-- Floating blobs -->
    <div class="absolute top-[-10%] left-[-5%] w-96 h-96 bg-blue-600/30 rounded-full blur-3xl animate-blob"></div>
    <div class="absolute bottom-[-10%] right-[-5%] w-96 h-96 bg-violet-600/30 rounded-full blur-3xl animate-blob" style="animation-delay:3s"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-72 h-72 bg-cyan-500/20 rounded-full blur-3xl animate-blob" style="animation-delay:6s"></div>

    <!-- Grid overlay -->
    <div class="absolute inset-0 opacity-[0.03]"
         style="background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:48px 48px"></div>

    <!-- Card -->
    <div class="relative w-full max-w-md animate-scale-in">

      <!-- Glow ring -->
      <div class="absolute -inset-1 bg-gradient-to-r from-blue-600 via-cyan-400 to-violet-600 rounded-3xl opacity-25 blur-xl"></div>

      <div class="relative card-glass p-8 rounded-3xl">

        <!-- Logo -->
        <div class="flex flex-col items-center mb-8 animate-fade-down">
          <div class="relative mb-4">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center shadow-lg shadow-blue-500/40">
              <span class="text-white font-extrabold text-2xl tracking-tight">OV</span>
            </div>
            <!-- Pulse ring -->
            <span class="absolute inset-0 rounded-2xl bg-blue-500/40 animate-ping" style="animation-duration:2.5s"></span>
          </div>
          <h1 class="text-2xl font-extrabold text-white tracking-tight">OpenVyapar ERP</h1>
          <p class="text-blue-300/80 text-sm mt-1 font-medium">Indian Business · GST Ready · Open Source</p>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleLogin" class="space-y-5">
          <!-- Email -->
          <div class="animate-fade-up delay-75">
            <label class="block text-sm font-semibold text-blue-200 mb-1.5">Email</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-3.5 flex items-center pointer-events-none">
                <MailIcon class="w-4 h-4 text-blue-400" />
              </div>
              <input v-model="form.email" type="email"
                class="w-full rounded-xl border border-white/10 bg-white/5 text-white placeholder:text-blue-300/40
                       pl-10 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400/60
                       focus:border-blue-400/60 transition-all duration-200 backdrop-blur-sm"
                placeholder="admin@openvyapar.in" required />
            </div>
          </div>

          <!-- Password -->
          <div class="animate-fade-up delay-150">
            <label class="block text-sm font-semibold text-blue-200 mb-1.5">Password</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-3.5 flex items-center pointer-events-none">
                <LockIcon class="w-4 h-4 text-blue-400" />
              </div>
              <input v-model="form.password" :type="showPwd ? 'text' : 'password'"
                class="w-full rounded-xl border border-white/10 bg-white/5 text-white placeholder:text-blue-300/40
                       pl-10 pr-12 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400/60
                       focus:border-blue-400/60 transition-all duration-200 backdrop-blur-sm"
                placeholder="••••••••" required />
              <button type="button" @click="showPwd = !showPwd"
                class="absolute inset-y-0 right-3.5 flex items-center text-blue-400 hover:text-blue-200 transition-colors">
                <EyeOffIcon v-if="showPwd" class="w-4 h-4" />
                <EyeIcon v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Error -->
          <Transition name="slide-down">
            <div v-if="error"
              class="flex items-center gap-2 text-red-300 text-sm bg-red-500/10 border border-red-500/20 px-4 py-3 rounded-xl">
              <AlertCircleIcon class="w-4 h-4 flex-shrink-0" />
              {{ error }}
            </div>
          </Transition>

          <!-- Submit -->
          <div class="animate-fade-up delay-300">
            <button type="submit"
              class="w-full py-3 px-6 rounded-xl font-bold text-sm text-white
                     bg-gradient-to-r from-blue-600 via-blue-500 to-cyan-500
                     hover:from-blue-500 hover:to-cyan-400 transition-all duration-300
                     shadow-lg shadow-blue-500/30 hover:shadow-blue-500/50
                     hover:-translate-y-0.5 active:translate-y-0
                     flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:transform-none"
              :disabled="loading">
              <span v-if="loading" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                Signing in…
              </span>
              <span v-else class="flex items-center gap-2">
                <LogInIcon class="w-4 h-4" />
                Sign In
              </span>
            </button>
          </div>
        </form>

        <!-- Footer -->
        <div class="mt-6 text-center animate-fade-up delay-500">
          <div class="flex items-center justify-center gap-4 text-xs text-blue-400/60">
            <span class="flex items-center gap-1"><ShieldCheckIcon class="w-3.5 h-3.5" /> AGPL v3</span>
            <span class="w-px h-3 bg-blue-400/20"></span>
            <span class="flex items-center gap-1"><IndianRupeeIcon class="w-3.5 h-3.5" /> GST Ready</span>
            <span class="w-px h-3 bg-blue-400/20"></span>
            <span class="flex items-center gap-1"><ZapIcon class="w-3.5 h-3.5" /> Open Source</span>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import {
  MailIcon, LockIcon, EyeIcon, EyeOffIcon, LogInIcon,
  AlertCircleIcon, ShieldCheckIcon, IndianRupeeIcon, ZapIcon,
} from 'lucide-vue-next'

const auth = useAuthStore()
const router = useRouter()

const form = ref({ email: '', password: '' })
const loading = ref(false)
const error = ref('')
const showPwd = ref(false)

async function handleLogin() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(form.value.email, form.value.password)
    router.push('/dashboard')
  } catch (e: any) {
    error.value = e.response?.data?.message ?? e.response?.data?.errors?.email?.[0] ?? 'Invalid credentials. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active {
  transition: all 0.3s ease;
}
.slide-down-enter-from, .slide-down-leave-to {
  opacity: 0;
  transform: translateY(-8px);
  max-height: 0;
}
</style>
