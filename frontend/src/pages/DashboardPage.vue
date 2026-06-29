<template>
  <div class="space-y-6">

    <!-- Welcome banner -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-800 p-6 text-white animate-fade-down">
      <div class="absolute inset-0 opacity-10"
           style="background-image:radial-gradient(circle at 30% 50%, white 1px, transparent 1px);background-size:32px 32px"></div>
      <div class="absolute -right-8 -top-8 w-40 h-40 rounded-full bg-white/5 blur-xl"></div>
      <div class="absolute right-16 bottom-0 w-24 h-24 rounded-full bg-white/5 blur-lg"></div>
      <div class="relative">
        <div class="flex items-center gap-2 mb-1">
          <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
          <span class="text-blue-200 text-xs font-semibold uppercase tracking-wider">Live</span>
        </div>
        <h2 class="text-xl font-extrabold">Good {{ greeting }}, {{ auth.user?.name?.split(' ')[0] }}!</h2>
        <p class="text-blue-200 text-sm mt-0.5">Here's what's happening with <strong>{{ auth.user?.company?.name }}</strong> today.</p>
        <div class="mt-3 flex items-center gap-4 text-xs text-blue-300">
          <span>FY 2025-26</span>
          <span class="w-px h-3 bg-blue-500"></span>
          <span>{{ todayDate }}</span>
          <span class="w-px h-3 bg-blue-500"></span>
          <span class="flex items-center gap-1">
            <div class="w-1.5 h-1.5 rounded-full bg-emerald-400"></div> GST Ready
          </span>
        </div>
      </div>
    </div>

    <!-- Money stat cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <StatCard title="Today's Sales"    :value="fmt(stats?.today_sales)"              color="blue"   icon="rupee"  :delay="0"   :bar-width="barPct(stats?.today_sales, stats?.month_sales)" />
      <StatCard title="Month Sales"      :value="fmt(stats?.month_sales)"              color="green"  icon="rupee"  :delay="75"  bar-width="60%" />
      <StatCard title="Receivable"       :value="fmt(stats?.outstanding_receivable)"   color="orange" icon="credit" :delay="150" bar-width="45%" />
      <StatCard title="Payable"          :value="fmt(stats?.outstanding_payable)"      color="red"    icon="arrow"  :delay="225" bar-width="30%" />
    </div>

    <!-- Count cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <CountCard v-for="(c, i) in countCards" :key="c.label"
        :label="c.label" :value="c.value" :icon="c.icon" :color="c.color"
        :delay="i * 60" />
    </div>

    <!-- Chart + Top Products -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

      <!-- Sales chart -->
      <div class="xl:col-span-2 card p-6 animate-fade-up delay-200">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Sales Trend</h2>
            <p class="text-xs text-gray-400 mt-0.5">Last 30 days</p>
          </div>
          <div class="flex items-center gap-1.5 px-3 py-1 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 text-xs font-semibold">
            <BarChart2Icon class="w-3.5 h-3.5" />
            Area Chart
          </div>
        </div>
        <apexchart type="area" height="220" :options="chartOptions" :series="chartSeries" />
      </div>

      <!-- Top Products -->
      <div class="card p-6 animate-fade-up delay-300">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Top Products</h2>
        <div v-if="stats?.top_products?.length" class="space-y-3">
          <div v-for="(p, i) in stats.top_products" :key="p.name"
               class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
                 :class="['bg-blue-500','bg-emerald-500','bg-violet-500','bg-orange-500','bg-rose-500'][i]">
              {{ i + 1 }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ p.name }}</p>
              <p class="text-xs text-gray-400">{{ p.qty }} units sold</p>
            </div>
            <span class="text-sm font-bold text-blue-600 dark:text-blue-400">{{ fmt(p.revenue) }}</span>
          </div>
        </div>
        <div v-else class="flex flex-col items-center justify-center h-40 text-center">
          <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
            <PackageIcon class="w-6 h-6 text-gray-400" />
          </div>
          <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No sales yet</p>
          <p class="text-xs text-gray-400 mt-1">Create your first invoice to see data</p>
        </div>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="card p-6 animate-fade-up delay-300">
      <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Quick Actions</h2>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <RouterLink v-for="action in quickActions" :key="action.label" :to="action.path"
          class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-dashed
                 border-gray-200 dark:border-gray-700 hover:border-blue-400 dark:hover:border-blue-600
                 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all duration-200 group">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white"
               :class="action.color">
            <component :is="action.icon" class="w-5 h-5" />
          </div>
          <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 text-center">
            {{ action.label }}
          </span>
        </RouterLink>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, defineComponent, h } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../api/client'
import StatCard from '../components/StatCard.vue'
import { useAuthStore } from '../stores/auth'
import {
  PackageIcon, UsersIcon, TruckIcon, AlertTriangleIcon,
  ReceiptIcon, ShoppingCartIcon, PlusIcon, BarChart2Icon,
} from 'lucide-vue-next'

const auth = useAuthStore()
const stats = ref<any>(null)

const fmt = (v: number | undefined) =>
  v !== undefined ? '₹' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 }) : '₹0'

const barPct = (a: any, b: any) => {
  if (!a || !b || Number(b) === 0) return '0%'
  return Math.min(100, Math.round((Number(a) / Number(b)) * 100)) + '%'
}

const todayDate = new Date().toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long' })
const greeting = computed(() => {
  const h = new Date().getHours()
  return h < 12 ? 'Morning' : h < 17 ? 'Afternoon' : 'Evening'
})

const countCards = computed(() => [
  { label: 'Products',   value: stats.value?.total_products  ?? '–', icon: PackageIcon,       color: 'text-blue-600   bg-blue-50   dark:bg-blue-900/20   dark:text-blue-400' },
  { label: 'Customers',  value: stats.value?.total_customers ?? '–', icon: UsersIcon,         color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 dark:text-emerald-400' },
  { label: 'Suppliers',  value: stats.value?.total_suppliers ?? '–', icon: TruckIcon,         color: 'text-violet-600  bg-violet-50  dark:bg-violet-900/20  dark:text-violet-400' },
  { label: 'Low Stock',  value: stats.value?.low_stock_count ?? '–', icon: AlertTriangleIcon, color: (stats.value?.low_stock_count ?? 0) > 0 ? 'text-rose-600 bg-rose-50 dark:bg-rose-900/20 dark:text-rose-400' : 'text-gray-500 bg-gray-100 dark:bg-gray-800 dark:text-gray-400' },
])

const quickActions = [
  { label: 'New Invoice',   path: '/sales/new',    icon: ReceiptIcon,     color: 'bg-gradient-to-br from-blue-500 to-blue-700' },
  { label: 'New Purchase',  path: '/purchases/new',icon: ShoppingCartIcon,color: 'bg-gradient-to-br from-violet-500 to-violet-700' },
  { label: 'Add Product',   path: '/products/new', icon: PackageIcon,     color: 'bg-gradient-to-br from-emerald-500 to-emerald-700' },
  { label: 'Add Customer',  path: '/customers',    icon: UsersIcon,       color: 'bg-gradient-to-br from-orange-500 to-orange-700' },
]

// Chart
const chartSeries = computed(() => {
  const data = stats.value?.sales_chart ?? []
  return [{
    name: 'Sales ₹',
    data: data.length
      ? data.map((r: any) => ({ x: r.date, y: Number(r.total) }))
      : Array.from({ length: 7 }, (_, i) => {
          const d = new Date(); d.setDate(d.getDate() - (6 - i))
          return { x: d.toISOString().split('T')[0], y: 0 }
        }),
  }]
})

const chartOptions = computed(() => ({
  chart: { toolbar: { show: false }, background: 'transparent', sparkline: { enabled: false }, animations: { enabled: true, easing: 'easeinout', speed: 800 } },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 3 },
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 100] } },
  colors: ['#3b82f6'],
  markers: { size: 4, colors: ['#3b82f6'], strokeColors: '#fff', strokeWidth: 2, hover: { size: 6 } },
  xaxis: { type: 'datetime', labels: { style: { colors: '#94a3b8', fontSize: '11px' }, datetimeUTC: false }, axisBorder: { show: false }, axisTicks: { show: false } },
  yaxis: { labels: { formatter: (v: number) => v === 0 ? '₹0' : '₹' + (v / 1000).toFixed(0) + 'k', style: { colors: '#94a3b8', fontSize: '11px' } } },
  tooltip: { x: { format: 'dd MMM yyyy' }, y: { formatter: (v: number) => '₹' + Number(v).toLocaleString('en-IN') } },
  grid: { borderColor: '#e2e8f020', strokeDashArray: 4, xaxis: { lines: { show: false } } },
  theme: { mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
}))

// Count card component (inline)
const CountCard = defineComponent({
  props: ['label', 'value', 'icon', 'color', 'delay'],
  setup(props) {
    return () => h('div', {
      class: 'card p-5 flex items-center gap-4 animate-fade-up hover:-translate-y-0.5 transition-all duration-200 cursor-default',
      style: `animation-delay:${props.delay}ms`,
    }, [
      h('div', { class: `w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 ${props.color}` },
        [h(props.icon, { class: 'w-6 h-6' })]),
      h('div', [
        h('p', { class: 'text-2xl font-extrabold text-gray-900 dark:text-white leading-none' }, props.value),
        h('p', { class: 'text-xs font-semibold text-gray-400 mt-1 uppercase tracking-wide' }, props.label),
      ]),
    ])
  },
})

onMounted(async () => {
  const { data } = await api.get('/dashboard')
  stats.value = data
})
</script>
