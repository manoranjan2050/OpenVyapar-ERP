<template>
  <div class="flex h-screen bg-gray-50 dark:bg-gray-950 overflow-hidden">

    <!-- ── Sidebar ── -->
    <aside class="w-64 flex-shrink-0 flex flex-col relative overflow-hidden
                  bg-gradient-to-b from-slate-900 via-slate-900 to-slate-950
                  border-r border-white/5">

      <!-- Subtle bg glow -->
      <div class="absolute top-0 left-0 w-full h-48 blur-3xl pointer-events-none" :style="{ backgroundColor: themeStore.themes.find(t=>t.key===themeStore.current)?.c600 + '1a' }"></div>
      <div class="absolute bottom-0 right-0 w-32 h-32 bg-violet-600/10 blur-2xl pointer-events-none"></div>

      <!-- Logo -->
      <div class="relative h-16 flex items-center px-5 border-b border-white/5 flex-shrink-0">
        <RouterLink to="/dashboard" class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700
                      flex items-center justify-center shadow-lg shadow-blue-500/30 flex-shrink-0">
            <span class="text-white font-extrabold text-sm">OV</span>
          </div>
          <div>
            <p class="text-white font-bold text-sm leading-tight">OpenVyapar</p>
            <p class="text-blue-400/70 text-[10px] font-medium">ERP System</p>
          </div>
        </RouterLink>
      </div>

      <!-- Company badge -->
      <div v-if="auth.user?.company" class="relative mx-3 mt-3 mb-1 px-3 py-2 rounded-xl
                bg-white/5 border border-white/5 flex items-center gap-2">
        <div class="w-6 h-6 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500
                    flex items-center justify-center flex-shrink-0">
          <BuildingIcon class="w-3.5 h-3.5 text-white" />
        </div>
        <p class="text-xs font-medium text-gray-300 truncate">{{ auth.user.company.name }}</p>
      </div>

      <!-- Nav -->
      <nav class="relative flex-1 px-3 py-3 space-y-0.5 overflow-y-auto">

        <template v-for="item in navItems" :key="item.path">
          <!-- Section divider -->
          <div v-if="item.divider" class="pt-3 pb-1 px-2">
            <p class="text-[10px] font-bold text-gray-600 uppercase tracking-widest">{{ item.divider }}</p>
          </div>

          <RouterLink :to="item.path" class="nav-link"
            :class="isActive(item.path) ? 'nav-link-active' : 'nav-link-inactive'">
            <!-- Active glow -->
            <div v-if="isActive(item.path)"
              class="absolute inset-0 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 opacity-100"></div>
            <div class="relative flex items-center gap-3 w-full">
              <component :is="item.icon" class="w-4 h-4 flex-shrink-0" />
              <span class="flex-1">{{ item.label }}</span>
              <span v-if="item.badge" class="text-[10px] font-bold bg-white/20 px-1.5 py-0.5 rounded-full">
                {{ item.badge }}
              </span>
            </div>
          </RouterLink>
        </template>
      </nav>

      <!-- Theme Picker -->
      <div class="relative px-3 pb-1 flex-shrink-0">
        <p class="text-[9px] font-bold text-gray-600 uppercase tracking-widest px-1 mb-1.5">Theme</p>
        <div class="flex flex-wrap gap-1.5 px-1">
          <button v-for="t in themeStore.themes" :key="t.key"
            @click="themeStore.setTheme(t.key)"
            :title="t.name"
            :style="{ backgroundColor: t.c600 }"
            :class="themeStore.current === t.key ? 'ring-2 ring-white ring-offset-1 ring-offset-slate-900 scale-110' : 'opacity-60 hover:opacity-100'"
            class="w-5 h-5 rounded-full transition-all duration-200 cursor-pointer" />
        </div>
      </div>

      <!-- User footer -->
      <div class="relative p-3 border-t border-white/5 flex-shrink-0 mt-2">
        <div class="flex items-center gap-3 px-2 py-2 rounded-xl hover:bg-white/5 transition-colors group">
          <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600
                      flex items-center justify-center shadow-lg shadow-blue-500/20 flex-shrink-0">
            <span class="text-white text-sm font-bold">{{ auth.user?.name?.charAt(0).toUpperCase() }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-gray-200 truncate">{{ auth.user?.name }}</p>
            <p class="text-[11px] text-gray-500 truncate">{{ auth.user?.roles?.[0] ?? 'admin' }}</p>
          </div>
          <button @click="handleLogout"
            class="text-gray-600 hover:text-red-400 transition-colors p-1 rounded-lg hover:bg-red-500/10"
            title="Logout">
            <LogOutIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </aside>

    <!-- ── Main ── -->
    <main class="flex-1 flex flex-col overflow-hidden">

      <!-- Header -->
      <header class="h-16 bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm
                     border-b border-gray-100 dark:border-gray-800
                     flex items-center justify-between px-6 flex-shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-1.5 h-6 rounded-full bg-gradient-to-b from-blue-500 to-blue-700"></div>
          <h1 class="text-base font-bold text-gray-900 dark:text-white">{{ currentPageTitle }}</h1>
        </div>

        <div class="flex items-center gap-2">
          <!-- GST indicator -->
          <div class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-xl
                      bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
            <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
            <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">GST Active</span>
          </div>

          <!-- Dark mode toggle -->
          <button @click="toggleDark()"
            class="relative w-9 h-9 flex items-center justify-center rounded-xl
                   bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700
                   text-gray-600 dark:text-gray-300 transition-all duration-200 hover:scale-105">
            <Transition name="icon-swap" mode="out-in">
              <SunIcon v-if="isDark" key="sun" class="w-4 h-4" />
              <MoonIcon v-else key="moon" class="w-4 h-4" />
            </Transition>
          </button>

          <!-- Notification bell (UI only) -->
          <button class="relative w-9 h-9 flex items-center justify-center rounded-xl
                         bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700
                         text-gray-600 dark:text-gray-300 transition-all duration-200 hover:scale-105">
            <BellIcon class="w-4 h-4" />
          </button>
        </div>
      </header>

      <!-- Page content -->
      <div class="flex-1 overflow-y-auto">
        <div class="p-6 max-w-screen-2xl mx-auto">
          <RouterView v-slot="{ Component }">
            <component :is="Component" :key="route.fullPath" />
          </RouterView>
        </div>
      </div>
    </main>
  </div>

  <!-- Force password change on first login -->
  <ChangePasswordModal v-if="auth.mustChangePassword" @done="auth.clearMustChangePassword()" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useDark, useToggle } from '@vueuse/core'
import {
  LayoutDashboardIcon, PackageIcon, UsersIcon, TruckIcon,
  ReceiptIcon, ShoppingCartIcon, WarehouseIcon, SettingsIcon,
  LogOutIcon, SunIcon, MoonIcon, BellIcon, BuildingIcon, BarChart3Icon,
  BookOpenIcon, ArrowUpDownIcon, RotateCcwIcon, UserCogIcon, FileCodeIcon, InfoIcon,
  BellRingIcon, ActivityIcon, Trash2Icon, DatabaseIcon, ClipboardListIcon, CloudIcon,
} from 'lucide-vue-next'
import { useAuthStore } from '../stores/auth'
import { useThemeStore } from '../stores/theme'
import ChangePasswordModal from '../components/ChangePasswordModal.vue'

const auth = useAuthStore()
const themeStore = useThemeStore()
const route = useRoute()
const router = useRouter()
const isDark = useDark()
const toggleDark = useToggle(isDark)

const navItems = [
  { path: '/dashboard', label: 'Dashboard', icon: LayoutDashboardIcon },
  { divider: 'Transactions', path: '', label: '', icon: null },
  { path: '/sales', label: 'Sales Invoices', icon: ReceiptIcon },
  { path: '/challans', label: 'Delivery Challans', icon: ClipboardListIcon },
  { path: '/purchases', label: 'Purchases', icon: ShoppingCartIcon },
  { path: '/credit-notes', label: 'Credit Notes', icon: RotateCcwIcon },
  { divider: 'Masters', path: '', label: '', icon: null },
  { path: '/products', label: 'Products', icon: PackageIcon },
  { path: '/inventory', label: 'Inventory', icon: WarehouseIcon },
  { path: '/stock-adjustments', label: 'Stock Adjust', icon: ArrowUpDownIcon },
  { path: '/customers', label: 'Customers', icon: UsersIcon },
  { path: '/suppliers', label: 'Suppliers', icon: TruckIcon },
  { divider: 'Accounts', path: '', label: '', icon: null },
  { path: '/ledger', label: 'Party Ledger', icon: BookOpenIcon },
  { divider: 'Reports & Export', path: '', label: '', icon: null },
  { path: '/reports', label: 'GST Reports', icon: BarChart3Icon },
  { path: '/tally-export', label: 'Tally Export', icon: FileCodeIcon },
  { divider: 'System', path: '', label: '', icon: null },
  { path: '/users', label: 'Users & Roles', icon: UserCogIcon },
  { path: '/settings', label: 'Settings', icon: SettingsIcon },
  { divider: 'Security', path: '', label: '', icon: null },
  { path: '/alerts', label: 'Alerts & Notifications', icon: BellRingIcon },
  { path: '/activity-log', label: 'Activity Log', icon: ActivityIcon },
  { path: '/trash', label: 'Recycle Bin', icon: Trash2Icon },
  { path: '/backup', label: 'Backup & Restore', icon: DatabaseIcon },
  { path: '/backup-sync', label: 'Cloud Sync', icon: CloudIcon },
  { path: '/about', label: 'About', icon: InfoIcon },
].filter(i => i.icon || i.divider)

const isActive = (path: string) =>
  path && (route.path === path || route.path.startsWith(path + '/'))

const currentPageTitle = computed(() => {
  const item = navItems.find(i => i.path && isActive(i.path))
  return item?.label ?? 'OpenVyapar ERP'
})

async function handleLogout() {
  auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.icon-swap-enter-active, .icon-swap-leave-active { transition: all 0.2s ease; }
.icon-swap-enter-from { opacity: 0; transform: rotate(-90deg) scale(0.6); }
.icon-swap-leave-to   { opacity: 0; transform: rotate(90deg) scale(0.6); }
</style>
