<template>
  <div class="max-w-4xl space-y-6 animate-fade-up">

    <!-- Header + Quick backup -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Backup & Restore</h1>
        <p class="text-sm text-gray-500 mt-0.5">Secure your data with local and manual backups</p>
      </div>
      <button @click="createBackup('manual')" :disabled="creating"
              class="btn-primary flex items-center gap-2 text-sm">
        <DatabaseIcon class="w-4 h-4" />
        <span>{{ creating ? 'Creating…' : 'Backup Now' }}</span>
      </button>
    </div>

    <!-- Flash -->
    <div v-if="flash" class="p-3 rounded-xl text-sm font-medium"
         :class="flashType === 'ok' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-700' : 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300 border border-rose-200 dark:border-rose-700'">
      {{ flash }}
    </div>

    <!-- Stats cards -->
    <div class="grid grid-cols-3 gap-4">
      <div class="card p-5 text-center">
        <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mx-auto mb-3">
          <ArchiveIcon class="w-5 h-5 text-blue-600 dark:text-blue-400" />
        </div>
        <p class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ bStats.total_backups ?? 0 }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Total Backups</p>
      </div>
      <div class="card p-5 text-center">
        <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mx-auto mb-3">
          <HardDriveIcon class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
        </div>
        <p class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ bStats.total_size ?? '0 B' }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Storage Used</p>
      </div>
      <div class="card p-5 text-center">
        <div class="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center mx-auto mb-3">
          <ClockIcon class="w-5 h-5 text-violet-600 dark:text-violet-400" />
        </div>
        <p class="text-lg font-extrabold text-gray-900 dark:text-white">{{ latestDate }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Last Backup</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl w-fit">
      <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
              :class="tab === t.key ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
              class="px-4 py-1.5 rounded-lg text-sm font-semibold transition-all">
        {{ t.label }}
      </button>
    </div>

    <!-- Tab: Backups List -->
    <div v-if="tab === 'backups'" class="card overflow-hidden">
      <div v-if="!backups.length" class="p-10 text-center text-gray-400">
        <ArchiveIcon class="w-10 h-10 mx-auto mb-2 text-gray-200 dark:text-gray-700" />
        <p>No backups yet. Click "Backup Now" to create your first backup.</p>
      </div>
      <div v-else>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Filename</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400 w-24">Type</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400 w-24">Size</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400 w-36">Created</th>
              <th class="px-4 py-3 w-28"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in backups" :key="b.filename"
                class="border-t border-gray-50 dark:border-gray-800/50 hover:bg-gray-50 dark:hover:bg-gray-800/20">
              <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300 truncate max-w-xs">
                {{ b.filename }}
              </td>
              <td class="px-4 py-3">
                <span :class="b.type === 'auto' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400'"
                      class="px-2 py-0.5 rounded-full text-xs font-bold capitalize">{{ b.type }}</span>
              </td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ b.size_human }}</td>
              <td class="px-4 py-3 text-gray-500 text-xs font-mono">{{ b.created_at?.slice(0, 16) }}</td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <a :href="`${apiBase}/api/backups/download/${b.filename}`" target="_blank"
                     class="text-xs px-2 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 font-semibold transition-all">
                    Download
                  </a>
                  <button @click="deleteBackup(b.filename)" class="text-gray-300 hover:text-rose-500 p-1 transition-colors">
                    <Trash2Icon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tab: Restore -->
    <div v-if="tab === 'restore'" class="space-y-4">

      <div class="card p-6 space-y-4">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
            <UploadIcon class="w-6 h-6 text-amber-600 dark:text-amber-400" />
          </div>
          <div>
            <h2 class="font-bold text-gray-900 dark:text-white">Restore from Backup File</h2>
            <p class="text-sm text-gray-500 mt-1">Upload a .zip or .sql backup file to restore your database.</p>
          </div>
        </div>

        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-sm text-rose-700 dark:text-rose-400">
          ⚠️ <strong>Warning:</strong> Restoring a backup will OVERWRITE your current database. All data created after the backup will be LOST. Take a fresh backup first!
        </div>

        <div class="border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl p-8 text-center hover:border-blue-400 dark:hover:border-blue-600 transition-colors"
             @dragover.prevent @drop.prevent="onDrop">
          <UploadIcon class="w-8 h-8 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
          <p class="text-sm text-gray-500 mb-3">Drop backup file here (.zip or .sql)</p>
          <label class="btn-secondary text-sm cursor-pointer">
            Browse File
            <input type="file" class="sr-only" accept=".zip,.sql" @change="onFileSelect" />
          </label>
          <p v-if="restoreFile" class="mt-3 text-sm font-medium text-emerald-600 dark:text-emerald-400">
            ✓ {{ restoreFile.name }} ({{ humanSize(restoreFile.size) }})
          </p>
        </div>

        <div class="flex gap-3">
          <button @click="restoreBackup" :disabled="!restoreFile || restoring"
                  class="btn-primary text-sm flex items-center gap-2 disabled:opacity-50">
            <DatabaseIcon class="w-4 h-4" />
            {{ restoring ? 'Restoring…' : 'Restore Database' }}
          </button>
          <button @click="createBackup('manual')" class="btn-secondary text-sm flex items-center gap-2">
            <ArchiveIcon class="w-4 h-4" /> Backup First
          </button>
        </div>
      </div>

      <!-- Restore from existing backup -->
      <div v-if="backups.length" class="card p-6">
        <h3 class="font-bold text-gray-800 dark:text-white mb-4">Restore from Saved Backup</h3>
        <div class="space-y-2">
          <div v-for="b in backups.slice(0, 5)" :key="b.filename"
               class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 dark:border-gray-800 hover:border-gray-200 dark:hover:border-gray-700">
            <ArchiveIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-mono text-gray-700 dark:text-gray-300 truncate">{{ b.filename }}</p>
              <p class="text-xs text-gray-400">{{ b.size_human }} · {{ b.created_at?.slice(0, 16) }}</p>
            </div>
            <button @click="confirmRestoreFrom = b" class="text-xs px-2.5 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 font-semibold transition-all">
              Restore
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Settings -->
    <div v-if="tab === 'settings'" class="card p-6 space-y-5">
      <h2 class="font-bold text-gray-900 dark:text-white">Auto-Backup Settings</h2>

      <div class="space-y-4">
        <label class="flex items-center justify-between p-4 rounded-xl border border-gray-100 dark:border-gray-800 hover:border-gray-200 dark:hover:border-gray-700 cursor-pointer">
          <div>
            <p class="font-semibold text-gray-800 dark:text-white text-sm">Enable Auto Backup</p>
            <p class="text-xs text-gray-500">Automatically backup at set intervals</p>
          </div>
          <div class="relative">
            <div @click="bSettings.auto_backup_enabled = !bSettings.auto_backup_enabled"
                 :class="bSettings.auto_backup_enabled ? 'bg-blue-500' : 'bg-gray-300 dark:bg-gray-600'"
                 class="w-11 h-6 rounded-full cursor-pointer transition-colors"></div>
            <div :class="bSettings.auto_backup_enabled ? 'translate-x-5' : 'translate-x-0.5'"
                 class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform pointer-events-none"></div>
          </div>
        </label>

        <div>
          <label class="form-label">Backup Interval</label>
          <select v-model="bSettings.auto_backup_interval" class="form-input">
            <option value="hourly">Every Hour</option>
            <option value="daily">Daily (Recommended)</option>
            <option value="weekly">Weekly</option>
          </select>
        </div>

        <div>
          <label class="form-label">Keep Last N Backups</label>
          <input v-model.number="bSettings.keep_last" type="number" min="1" max="100" class="form-input w-32" />
          <p class="text-xs text-gray-400 mt-1">Older backups are auto-deleted to save space</p>
        </div>

        <label class="flex items-center justify-between p-4 rounded-xl border border-gray-100 dark:border-gray-800 cursor-pointer">
          <div>
            <p class="font-semibold text-gray-800 dark:text-white text-sm">Backup on Session Close</p>
            <p class="text-xs text-gray-500">Create a backup when the browser/app is closed (requires service worker)</p>
          </div>
          <div class="relative">
            <div @click="bSettings.backup_on_close = !bSettings.backup_on_close"
                 :class="bSettings.backup_on_close ? 'bg-blue-500' : 'bg-gray-300 dark:bg-gray-600'"
                 class="w-11 h-6 rounded-full cursor-pointer transition-colors"></div>
            <div :class="bSettings.backup_on_close ? 'translate-x-5' : 'translate-x-0.5'"
                 class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform pointer-events-none"></div>
          </div>
        </label>

        <!-- Backup location info -->
        <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-sm">
          <p class="font-semibold text-blue-800 dark:text-blue-300 mb-2">Backup Storage Location</p>
          <p class="text-blue-700 dark:text-blue-400 font-mono text-xs break-all">{{ bStats.backup_dir ?? 'storage/app/backups/' }}</p>
          <p class="text-blue-600 dark:text-blue-500 text-xs mt-2">
            💡 For extra safety, also copy this folder to an external drive or Google Drive periodically.
          </p>
        </div>
      </div>

      <button @click="saveSettings" class="btn-primary text-sm">Save Settings</button>
    </div>

    <!-- Confirm restore from saved -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="confirmRestoreFrom" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="confirmRestoreFrom = null">
          <div class="card max-w-sm w-full p-6 space-y-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mx-auto">
              <DatabaseIcon class="w-6 h-6 text-amber-600 dark:text-amber-400" />
            </div>
            <div class="text-center">
              <h3 class="font-bold text-gray-900 dark:text-white">Restore from Backup?</h3>
              <p class="text-sm text-gray-500 mt-1">This will overwrite your current database with <strong>{{ confirmRestoreFrom?.filename }}</strong>.</p>
            </div>
            <div class="flex gap-2">
              <button @click="restoreFromSaved(confirmRestoreFrom.filename); confirmRestoreFrom = null" class="btn-primary flex-1 text-sm">Yes, Restore</button>
              <button @click="confirmRestoreFrom = null" class="btn-secondary flex-1 text-sm">Cancel</button>
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
import { DatabaseIcon, ArchiveIcon, HardDriveIcon, ClockIcon, UploadIcon, Trash2Icon } from 'lucide-vue-next'

const apiBase = import.meta.env.VITE_API_URL?.replace('/api', '') || 'http://localhost:8000'

const tab         = ref('backups')
const tabs        = [
  { key: 'backups',  label: 'My Backups' },
  { key: 'restore',  label: 'Restore' },
  { key: 'settings', label: 'Settings' },
]
const backups     = ref<any[]>([])
const bStats      = ref<any>({})
const bSettings   = ref<any>({ auto_backup_enabled: false, auto_backup_interval: 'daily', keep_last: 10, backup_on_close: true })
const creating    = ref(false)
const restoring   = ref(false)
const restoreFile = ref<File | null>(null)
const flash       = ref('')
const flashType   = ref<'ok'|'err'>('ok')
const confirmRestoreFrom = ref<any>(null)

const latestDate = computed(() => {
  if (!bStats.value.latest) return 'Never'
  return new Date(bStats.value.latest).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
})

function showFlash(msg: string, type: 'ok'|'err' = 'ok') {
  flash.value = msg; flashType.value = type
  setTimeout(() => flash.value = '', 4000)
}

async function loadBackups() {
  const { data } = await api.get('/backups')
  backups.value = data
}

async function loadStats() {
  const { data } = await api.get('/backups/stats')
  bStats.value = data
}

async function loadSettings() {
  const { data } = await api.get('/backups/settings')
  bSettings.value = data
}

async function createBackup(label = 'manual') {
  creating.value = true
  try {
    const { data } = await api.post('/backups/create', { label })
    showFlash(`✓ ${data.message} · ${data.size_human}`)
    await loadBackups(); await loadStats()
  } catch { showFlash('Backup failed. Check server logs.', 'err') }
  finally { creating.value = false }
}

async function deleteBackup(filename: string) {
  await api.delete(`/backups/${filename}`)
  showFlash('Backup deleted.')
  loadBackups(); loadStats()
}

async function saveSettings() {
  await api.post('/backups/settings', bSettings.value)
  showFlash('Settings saved!')
}

function onFileSelect(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (f) restoreFile.value = f
}

function onDrop(e: DragEvent) {
  const f = e.dataTransfer?.files?.[0]
  if (f) restoreFile.value = f
}

async function restoreBackup() {
  if (!restoreFile.value) return
  restoring.value = true
  const form = new FormData()
  form.append('file', restoreFile.value)
  try {
    const { data } = await api.post('/backups/restore', form, { headers: { 'Content-Type': 'multipart/form-data' } })
    showFlash(data.message)
    restoreFile.value = null
  } catch (e: any) {
    showFlash(e.response?.data?.message || 'Restore failed.', 'err')
  } finally { restoring.value = false }
}

async function restoreFromSaved(filename: string) {
  // Download the file as blob, then upload it back as restore
  const res = await api.get(`/backups/download/${filename}`, { responseType: 'blob' })
  const file = new File([res.data], filename, { type: 'application/zip' })
  restoreFile.value = file
  tab.value = 'restore'
  showFlash('File loaded. Click "Restore Database" to proceed.')
}

function humanSize(bytes: number): string {
  if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB'
  if (bytes >= 1024)    return (bytes / 1024).toFixed(0) + ' KB'
  return bytes + ' B'
}

onMounted(() => { loadBackups(); loadStats(); loadSettings() })
</script>
