<template>
  <div class="space-y-6">
    <div class="page-header animate-fade-down">
      <div>
        <h1 class="page-title">Data Sync & Cloud Backup</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Keep your data safe across multiple locations automatically</p>
      </div>
      <div class="flex gap-2">
        <button @click="loadProviders" class="btn-secondary text-xs">
          <RefreshCwIcon class="w-4 h-4 mr-1" /> Refresh Status
        </button>
      </div>
    </div>

    <!-- Global flash -->
    <Transition name="fade">
      <div v-if="flash" :class="flashOk ? 'bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400' : 'bg-rose-50 border-rose-200 text-rose-700 dark:bg-rose-900/20 dark:border-rose-800 dark:text-rose-400'"
        class="flex items-center gap-2 px-4 py-3 rounded-xl border text-sm font-semibold">
        <CheckCircleIcon v-if="flashOk" class="w-4 h-4" />
        <AlertCircleIcon v-else class="w-4 h-4" />
        {{ flash }}
      </div>
    </Transition>

    <!-- Info Banner -->
    <div class="card p-4 flex items-start gap-3 bg-blue-50/50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-800">
      <InfoIcon class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
      <p class="text-sm text-blue-700 dark:text-blue-300">
        Backup files are automatically synced to all enabled providers after each backup creation.
        You can also manually sync any backup from the <a href="/backup" @click.prevent="$router.push('/backup')" class="underline font-semibold">Backup page</a>.
        Cloud providers (Google Drive, Dropbox, OneDrive) require a one-time token setup.
      </p>
    </div>

    <!-- Providers Grid -->
    <div class="grid gap-4 md:grid-cols-2">
      <div v-for="p in providers" :key="p.provider" class="card p-5 space-y-4 animate-fade-up">
        <!-- Provider header -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" :class="providerMeta[p.provider].bg">
              <component :is="providerMeta[p.provider].icon" class="w-5 h-5" :class="providerMeta[p.provider].color" />
            </div>
            <div>
              <p class="font-bold text-gray-900 dark:text-white">{{ providerMeta[p.provider].label }}</p>
              <p class="text-xs text-gray-400">{{ providerMeta[p.provider].desc }}</p>
            </div>
          </div>
          <!-- Toggle -->
          <button @click="toggleEnabled(p)" class="relative inline-flex h-6 w-11 rounded-full transition-colors"
            :class="p.enabled ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-700'">
            <span class="inline-block h-5 w-5 mt-0.5 rounded-full bg-white shadow transform transition-transform"
              :class="p.enabled ? 'translate-x-5 ml-0.5' : 'translate-x-0.5'"></span>
          </button>
        </div>

        <!-- Status badge -->
        <div v-if="p.last_synced_at" class="flex items-center gap-2 text-xs">
          <span :class="p.last_sync_status === 'success' ? 'badge-green' : 'badge-red'">
            {{ p.last_sync_status === 'success' ? 'Last sync OK' : 'Last sync failed' }}
          </span>
          <span class="text-gray-400">{{ formatDate(p.last_synced_at) }}</span>
          <span v-if="p.last_sync_status !== 'success'" class="text-rose-600 truncate max-w-xs">{{ p.last_sync_message }}</span>
        </div>
        <div v-else class="text-xs text-gray-400">Never synced</div>

        <!-- Config fields -->
        <div v-if="p.showConfig" class="space-y-3 border-t border-gray-100 dark:border-gray-800 pt-3">

          <!-- EMAIL -->
          <template v-if="p.provider === 'email'">
            <div class="grid grid-cols-2 gap-2">
              <div><label class="label">SMTP Host</label><input v-model="p.config.host" class="input text-sm" placeholder="smtp.gmail.com" /></div>
              <div><label class="label">Port</label><input v-model="p.config.port" class="input text-sm" placeholder="587" /></div>
              <div><label class="label">Username / Email</label><input v-model="p.config.username" class="input text-sm" placeholder="you@gmail.com" /></div>
              <div><label class="label">Password / App Password</label><input v-model="p.config.password" type="password" class="input text-sm" placeholder="••••••••" /></div>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div><label class="label">Send From</label><input v-model="p.config.from_email" class="input text-sm" placeholder="you@gmail.com" /></div>
              <div><label class="label">Send To</label><input v-model="p.config.to_email" class="input text-sm" placeholder="backup@yourdomain.com" /></div>
            </div>
            <div class="text-xs text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg p-2">
              Gmail: Use an <b>App Password</b> (Google Account → Security → 2-Step → App Passwords).
              Encryption: TLS on port 587.
            </div>
          </template>

          <!-- LOCAL FOLDERS -->
          <template v-if="p.provider === 'local'">
            <div class="space-y-2">
              <label class="label">Backup Folders (main copy + carbon copies)</label>
              <div v-for="(path, i) in (p.config.paths || [''])" :key="i" class="flex gap-2">
                <input :value="path" @input="updateLocalPath(p, i, $event.target.value)" class="input text-sm flex-1" :placeholder="i === 0 ? 'C:\\Users\\You\\OneDrive\\Backups (Main copy)' : 'D:\\USB Drive\\Backups (Carbon copy ' + i + ')'" />
                <button v-if="i > 0" @click="removeLocalPath(p, i)" class="btn-icon text-rose-500"><XIcon class="w-4 h-4" /></button>
                <span v-else class="text-xs text-gray-400 self-center font-semibold whitespace-nowrap">Main</span>
              </div>
              <button @click="addLocalPath(p)" class="text-xs text-blue-600 dark:text-blue-400 font-semibold hover:underline">
                + Add another folder (carbon copy)
              </button>
            </div>
            <div class="text-xs text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg p-2">
              The first path is the <b>main copy</b>. Additional paths are <b>carbon copies</b> (USB drive, external HDD, network share, etc.).
              Folders are created automatically if they don't exist.
            </div>
          </template>

          <!-- GOOGLE DRIVE -->
          <template v-if="p.provider === 'google_drive'">
            <div class="grid grid-cols-2 gap-2">
              <div><label class="label">Client ID</label><input v-model="p.config.client_id" class="input text-sm" placeholder="xxx.apps.googleusercontent.com" /></div>
              <div><label class="label">Client Secret</label><input v-model="p.config.client_secret" type="password" class="input text-sm" placeholder="••••••••" /></div>
            </div>
            <div><label class="label">Access Token (auto-filled after OAuth)</label><input v-model="p.config.access_token" class="input text-sm font-mono text-xs" placeholder="ya29.xxxx..." /></div>
            <div><label class="label">Folder ID (optional – leave empty for root)</label><input v-model="p.config.folder_id" class="input text-sm font-mono text-xs" placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms" /></div>
            <button @click="googleOAuth(p)" class="btn-secondary text-sm w-full justify-center">
              <ExternalLinkIcon class="w-4 h-4 mr-1" /> Authorise with Google (opens popup)
            </button>
            <div class="text-xs text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg p-2">
              Create a project at <b>console.cloud.google.com</b> → Enable Google Drive API → Create OAuth 2.0 credentials.
              Add <b>http://localhost:8000/api/backup-sync/google/callback</b> as a redirect URI.
            </div>
          </template>

          <!-- DROPBOX -->
          <template v-if="p.provider === 'dropbox'">
            <div><label class="label">Access Token</label><input v-model="p.config.access_token" type="password" class="input text-sm font-mono text-xs" placeholder="sl.xxxx..." /></div>
            <div><label class="label">Upload Folder</label><input v-model="p.config.folder" class="input text-sm" placeholder="/OpenVyapar Backups" /></div>
            <div class="text-xs text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg p-2">
              Go to <b>dropbox.com/developers</b> → Create App → Generate Access Token.
              Choose "Full Dropbox" or "App folder" scope with files.content.write permission.
            </div>
          </template>

          <!-- ONEDRIVE -->
          <template v-if="p.provider === 'onedrive'">
            <div><label class="label">Access Token (Microsoft Graph)</label><input v-model="p.config.access_token" type="password" class="input text-sm font-mono text-xs" placeholder="EwBIA8l6..." /></div>
            <div><label class="label">Upload Folder</label><input v-model="p.config.folder" class="input text-sm" placeholder="OpenVyapar Backups" /></div>
            <div class="text-xs text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg p-2">
              Go to <b>portal.azure.com</b> → App registrations → New registration → API permissions → Microsoft Graph → Files.ReadWrite.
              Then use OAuth or generate a token via <b>Graph Explorer</b> (graph.microsoft.com/v1.0/me).
            </div>
          </template>

          <!-- GITHUB -->
          <template v-if="p.provider === 'github'">
            <div><label class="label">Personal Access Token</label><input v-model="p.config.access_token" type="password" class="input text-sm font-mono text-xs" placeholder="ghp_xxxx..." /></div>
            <div class="grid grid-cols-2 gap-2">
              <div><label class="label">GitHub Username / Org</label><input v-model="p.config.owner" class="input text-sm" placeholder="manoranjan2050" /></div>
              <div><label class="label">Repository Name</label><input v-model="p.config.repo" class="input text-sm" placeholder="openvyapar-backups" /></div>
            </div>
            <div class="text-xs text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg p-2">
              Go to <b>github.com → Settings → Developer settings → Personal access tokens</b>.
              Grant <b>repo</b> scope. Create a <b>private</b> repo for your backups (e.g. "openvyapar-backups").
            </div>
          </template>

          <!-- Actions -->
          <div class="flex gap-2 pt-1">
            <button @click="saveProvider(p)" :disabled="p.saving" class="btn-primary text-sm flex-1 justify-center">
              {{ p.saving ? 'Saving…' : 'Save Settings' }}
            </button>
            <button @click="testProvider(p)" :disabled="p.testing" class="btn-secondary text-sm">
              {{ p.testing ? 'Testing…' : 'Test Connection' }}
            </button>
          </div>
          <p v-if="p.testResult" class="text-xs font-semibold" :class="p.testOk ? 'text-emerald-600' : 'text-rose-600'">{{ p.testResult }}</p>
        </div>

        <!-- Expand/collapse config -->
        <button @click="p.showConfig = !p.showConfig" class="text-xs text-blue-600 dark:text-blue-400 font-semibold hover:underline w-full text-left">
          {{ p.showConfig ? '▲ Hide settings' : '▼ Configure' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../api/client'
import {
  RefreshCwIcon, CheckCircleIcon, AlertCircleIcon, InfoIcon,
  XIcon, ExternalLinkIcon,
  MailIcon, FolderIcon, CloudIcon, GithubIcon
} from 'lucide-vue-next'

const flash   = ref('')
const flashOk = ref(true)
const providers = ref<any[]>([])

const providerMeta: Record<string, any> = {
  email:        { label: 'Email Backup',   desc: 'Send backup as email attachment (SMTP)',      icon: MailIcon,     bg: 'bg-blue-50 dark:bg-blue-900/20',    color: 'text-blue-600 dark:text-blue-400' },
  local:        { label: 'Local Folders',  desc: 'Main copy + carbon copies to local paths',   icon: FolderIcon,   bg: 'bg-amber-50 dark:bg-amber-900/20',  color: 'text-amber-600 dark:text-amber-400' },
  google_drive: { label: 'Google Drive',   desc: 'Upload to your Google Drive',                 icon: CloudIcon,    bg: 'bg-emerald-50 dark:bg-emerald-900/20', color: 'text-emerald-600 dark:text-emerald-400' },
  dropbox:      { label: 'Dropbox',        desc: 'Upload to your Dropbox account',              icon: CloudIcon,    bg: 'bg-blue-50 dark:bg-blue-900/20',    color: 'text-blue-700 dark:text-blue-400' },
  onedrive:     { label: 'OneDrive',       desc: 'Upload to Microsoft OneDrive',                icon: CloudIcon,    bg: 'bg-sky-50 dark:bg-sky-900/20',      color: 'text-sky-600 dark:text-sky-400' },
  github:       { label: 'GitHub',         desc: 'Push to a private GitHub repository',         icon: GithubIcon,   bg: 'bg-gray-100 dark:bg-gray-800',      color: 'text-gray-700 dark:text-gray-300' },
}

async function loadProviders() {
  const { data } = await api.get('/backup-sync')
  providers.value = data.map((p: any) => ({
    ...p,
    showConfig: false,
    saving: false,
    testing: false,
    testResult: '',
    testOk: false,
    config: { ...p.config, paths: p.config.paths ?? [''] },
  }))
}

async function toggleEnabled(p: any) {
  p.enabled = !p.enabled
  await api.post('/backup-sync/save', { provider: p.provider, enabled: p.enabled, config: p.config })
  showFlash(`${providerMeta[p.provider].label} ${p.enabled ? 'enabled' : 'disabled'}`, true)
}

async function saveProvider(p: any) {
  p.saving = true
  try {
    await api.post('/backup-sync/save', { provider: p.provider, enabled: p.enabled, config: p.config })
    showFlash('Settings saved for ' + providerMeta[p.provider].label, true)
  } catch (e: any) {
    showFlash(e.response?.data?.message ?? 'Save failed', false)
  } finally {
    p.saving = false
  }
}

async function testProvider(p: any) {
  p.testing = true
  p.testResult = ''
  try {
    const { data } = await api.post('/backup-sync/test', { provider: p.provider, config: p.config })
    p.testResult = data.message
    p.testOk = true
  } catch (e: any) {
    p.testResult = e.response?.data?.message ?? 'Test failed'
    p.testOk = false
  } finally {
    p.testing = false
  }
}

function addLocalPath(p: any) {
  if (!p.config.paths) p.config.paths = ['']
  p.config.paths.push('')
}

function removeLocalPath(p: any, i: number) {
  p.config.paths.splice(i, 1)
}

function updateLocalPath(p: any, i: number, val: string) {
  if (!p.config.paths) p.config.paths = ['']
  p.config.paths[i] = val
}

async function googleOAuth(p: any) {
  if (!p.config.client_id) { alert('Please enter your Google Client ID first.'); return }
  await saveProvider(p)
  const { data } = await api.get('/backup-sync/google/auth-url', { params: { client_id: p.config.client_id } })
  const popup = window.open(data.url, 'google_auth', 'width=600,height=700')
  window.addEventListener('message', async (e) => {
    if (e.data === 'google_auth_done') {
      popup?.close()
      await loadProviders()
      showFlash('Google Drive authorised successfully!', true)
    }
  }, { once: true })
}

function formatDate(d: string) {
  return new Date(d).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })
}

function showFlash(msg: string, ok: boolean) {
  flash.value = msg
  flashOk.value = ok
  setTimeout(() => flash.value = '', 4000)
}

onMounted(loadProviders)
</script>
