<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
      <div class="card w-full max-w-lg shadow-2xl flex flex-col" style="max-height: 80vh">

        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex-shrink-0">
          <div class="flex items-center gap-2">
            <FolderOpenIcon class="w-5 h-5 text-amber-500" />
            <h3 class="font-bold text-gray-900 dark:text-white text-sm">Select Backup Folder</h3>
          </div>
          <button @click="$emit('cancel')" class="btn-icon"><XIcon class="w-4 h-4" /></button>
        </div>

        <!-- Quick shortcuts -->
        <div class="px-4 pt-3 pb-2 flex gap-2 flex-wrap flex-shrink-0 border-b border-gray-100 dark:border-gray-800">
          <button v-for="s in shortcuts" :key="s.path" @click="navigate(s.path)"
            class="text-xs px-2.5 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-100 dark:hover:bg-blue-900/30 hover:text-blue-700 dark:hover:text-blue-400 font-medium transition-all flex items-center gap-1">
            <HomeIcon v-if="s.label === 'Desktop' || s.label === 'Documents'" class="w-3 h-3" />
            <HardDriveIcon v-else-if="s.path.match(/^[A-Z]:\\/)" class="w-3 h-3" />
            <CloudIcon v-else-if="s.label === 'OneDrive'" class="w-3 h-3" />
            <FolderIcon v-else class="w-3 h-3" />
            {{ s.label }}
          </button>
        </div>

        <!-- Breadcrumb -->
        <div class="px-4 py-2 flex items-center gap-1 flex-wrap flex-shrink-0 bg-gray-50 dark:bg-gray-900/50">
          <button v-if="parentPath" @click="navigate(parentPath)" class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-all">
            <ChevronLeftIcon class="w-4 h-4 text-gray-500" />
          </button>
          <template v-for="(crumb, i) in breadcrumb" :key="crumb.path">
            <span v-if="i > 0" class="text-gray-300 dark:text-gray-600 text-xs">/</span>
            <button @click="navigate(crumb.path)"
              class="text-xs font-medium px-1.5 py-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-all"
              :class="i === breadcrumb.length - 1 ? 'text-gray-900 dark:text-white' : 'text-blue-600 dark:text-blue-400'">
              {{ crumb.label }}
            </button>
          </template>
        </div>

        <!-- Current path input -->
        <div class="px-4 py-2 flex-shrink-0 border-b border-gray-100 dark:border-gray-800">
          <div class="flex gap-2">
            <input v-model="manualPath" @keydown.enter="navigate(manualPath)"
              class="input text-xs font-mono flex-1" placeholder="Type a path and press Enter…" />
            <button @click="navigate(manualPath)" class="btn-secondary text-xs px-3">Go</button>
          </div>
          <p v-if="error" class="text-xs text-rose-600 dark:text-rose-400 mt-1">{{ error }}</p>
        </div>

        <!-- Folder list -->
        <div class="overflow-y-auto flex-1 px-2 py-2">
          <div v-if="loading" class="flex items-center justify-center py-8">
            <div class="animate-spin w-6 h-6 rounded-full border-2 border-blue-500 border-t-transparent"></div>
          </div>
          <div v-else-if="dirs.length === 0" class="py-8 text-center text-sm text-gray-400">
            No subfolders found (this is the deepest level)
          </div>
          <button v-else v-for="d in dirs" :key="d.path" @click="navigate(d.path)"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all group text-left">
            <FolderIcon class="w-5 h-5 text-amber-400 group-hover:text-amber-500 flex-shrink-0" />
            <span class="text-sm text-gray-700 dark:text-gray-200 group-hover:text-gray-900 dark:group-hover:text-white truncate">{{ d.name }}</span>
            <ChevronRightIcon class="w-4 h-4 text-gray-300 dark:text-gray-600 ml-auto flex-shrink-0 group-hover:text-blue-500" />
          </button>
        </div>

        <!-- Footer: select current or create new -->
        <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-800 flex gap-2 flex-shrink-0 bg-gray-50/80 dark:bg-gray-900/50">
          <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400 font-medium">Selected folder:</p>
            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate font-mono">{{ currentPath }}</p>
          </div>
          <button @click="$emit('select', currentPath)" class="btn-primary text-sm flex-shrink-0">
            <CheckIcon class="w-4 h-4 mr-1" /> Select This Folder
          </button>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../api/client'
import {
  FolderIcon, FolderOpenIcon, XIcon, ChevronLeftIcon, ChevronRightIcon,
  CheckIcon, HomeIcon, HardDriveIcon, CloudIcon
} from 'lucide-vue-next'

const props = defineProps<{ initialPath?: string }>()
const emit  = defineEmits<{ select: [path: string]; cancel: [] }>()

const currentPath = ref(props.initialPath || '')
const parentPath  = ref<string | null>(null)
const breadcrumb  = ref<{ label: string; path: string }[]>([])
const dirs        = ref<{ name: string; path: string }[]>([])
const shortcuts   = ref<{ label: string; path: string }[]>([])
const loading     = ref(false)
const error       = ref('')
const manualPath  = ref(props.initialPath || '')

async function navigate(path: string) {
  if (!path) return
  loading.value = true
  error.value   = ''
  try {
    const { data } = await api.get('/backup-sync/browse-folder', { params: { path } })
    currentPath.value = data.current
    manualPath.value  = data.current
    parentPath.value  = data.parent
    breadcrumb.value  = data.breadcrumb
    dirs.value        = data.dirs
    if (data.shortcuts?.length) shortcuts.value = data.shortcuts
  } catch (e: any) {
    error.value = e.response?.data?.error ?? 'Could not open folder'
  } finally {
    loading.value = false
  }
}

onMounted(() => navigate(props.initialPath || '~'))
</script>
