<template>
  <div class="relative overflow-hidden rounded-2xl p-5 text-white shadow-lg transition-all duration-300
              hover:-translate-y-1 hover:shadow-xl cursor-default animate-fade-up"
       :class="gradientClass" :style="{ animationDelay: delay + 'ms' }">

    <!-- Background pattern -->
    <div class="absolute inset-0 opacity-10"
         style="background-image:radial-gradient(circle at 80% 20%, white 1px, transparent 1px);background-size:24px 24px"></div>

    <!-- Large icon watermark -->
    <div class="absolute -right-3 -top-3 opacity-10">
      <component :is="iconComponent" class="w-24 h-24" />
    </div>

    <!-- Content -->
    <div class="relative">
      <div class="flex items-center justify-between mb-3">
        <p class="text-xs font-semibold uppercase tracking-wider text-white/70">{{ title }}</p>
        <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center">
          <component :is="iconComponent" class="w-4 h-4" />
        </div>
      </div>

      <p class="text-2xl font-extrabold tracking-tight leading-none">{{ value }}</p>

      <div v-if="subtext" class="mt-2 flex items-center gap-1 text-xs text-white/60 font-medium">
        <TrendingUpIcon v-if="trend === 'up'" class="w-3 h-3 text-white/80" />
        <TrendingDownIcon v-if="trend === 'down'" class="w-3 h-3 text-white/60" />
        {{ subtext }}
      </div>
    </div>

    <!-- Shimmer bar at bottom -->
    <div class="absolute bottom-0 left-0 h-0.5 w-full bg-white/20 rounded-full"></div>
    <div class="absolute bottom-0 left-0 h-0.5 bg-white/50 rounded-full transition-all duration-500"
         :style="{ width: barWidth }"></div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  IndianRupeeIcon, PackageIcon, UsersIcon, TruckIcon,
  AlertTriangleIcon, TrendingUpIcon, TrendingDownIcon, CreditCardIcon,
  ArrowDownIcon, BarChart2Icon,
} from 'lucide-vue-next'

const props = defineProps<{
  title: string
  value: string
  color?: string
  icon?: string
  subtext?: string
  trend?: 'up' | 'down' | 'neutral'
  barWidth?: string
  delay?: number
}>()

const gradientClass = computed(() => ({
  blue:   'bg-gradient-to-br from-blue-500 to-blue-700',
  green:  'bg-gradient-to-br from-emerald-500 to-emerald-700',
  orange: 'bg-gradient-to-br from-orange-400 to-orange-600',
  red:    'bg-gradient-to-br from-rose-500 to-rose-700',
  purple: 'bg-gradient-to-br from-violet-500 to-violet-700',
  teal:   'bg-gradient-to-br from-teal-500 to-teal-700',
  amber:  'bg-gradient-to-br from-amber-500 to-amber-700',
}[props.color ?? 'blue']))

const iconComponent = computed(() => ({
  rupee:    IndianRupeeIcon,
  package:  PackageIcon,
  users:    UsersIcon,
  truck:    TruckIcon,
  alert:    AlertTriangleIcon,
  credit:   CreditCardIcon,
  arrow:    ArrowDownIcon,
  chart:    BarChart2Icon,
}[props.icon ?? 'rupee']))
</script>
