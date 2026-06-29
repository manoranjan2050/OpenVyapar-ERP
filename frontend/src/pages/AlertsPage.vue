<template>
  <div class="max-w-4xl space-y-6 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Alert & Notification System</h1>
        <p class="text-sm text-gray-500 mt-0.5">Telegram + Email alerts for stock, payments, sales, and more</p>
      </div>
      <div class="flex gap-2">
        <button @click="runStockCheck" :disabled="checking" class="btn-secondary flex items-center gap-2 text-sm">
          <PackageIcon class="w-4 h-4" /> Check Stock Now
        </button>
        <button @click="runOverdueCheck" :disabled="checking" class="btn-secondary flex items-center gap-2 text-sm">
          <AlertCircleIcon class="w-4 h-4" /> Check Overdue
        </button>
      </div>
    </div>

    <!-- Flash -->
    <div v-if="flash" class="p-3 rounded-xl text-sm font-medium"
         :class="flashType === 'ok' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-700' : 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300 border border-rose-200 dark:border-rose-700'">
      {{ flash }}
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl w-fit">
      <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
              :class="tab === t.key ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
              class="px-4 py-1.5 rounded-lg text-sm font-semibold transition-all">
        {{ t.label }}
      </button>
    </div>

    <!-- Tab: Telegram -->
    <div v-if="tab === 'telegram'" class="card p-6 space-y-5">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center flex-shrink-0">
          <SendIcon class="w-6 h-6 text-sky-600 dark:text-sky-400" />
        </div>
        <div class="flex-1">
          <h2 class="font-bold text-gray-900 dark:text-white">Telegram Bot Setup</h2>
          <p class="text-sm text-gray-500 mt-1">Get instant alerts on Telegram. Free, fast, works on mobile.</p>
        </div>
        <label class="flex items-center gap-2 cursor-pointer">
          <span class="text-sm text-gray-600 dark:text-gray-400">Enable</span>
          <div class="relative">
            <input type="checkbox" v-model="settings.telegram_enabled" class="sr-only" />
            <div @click="settings.telegram_enabled = !settings.telegram_enabled"
                 :class="settings.telegram_enabled ? 'bg-sky-500' : 'bg-gray-300 dark:bg-gray-600'"
                 class="w-10 h-5 rounded-full cursor-pointer transition-colors"></div>
            <div :class="settings.telegram_enabled ? 'translate-x-5' : 'translate-x-0.5'"
                 class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform pointer-events-none"></div>
          </div>
        </label>
      </div>

      <!-- How-to guide -->
      <div class="p-4 rounded-xl bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800 text-sm space-y-2">
        <p class="font-semibold text-sky-800 dark:text-sky-300">How to get your Bot Token &amp; Chat ID:</p>
        <ol class="text-sky-700 dark:text-sky-400 list-decimal list-inside space-y-1">
          <li>Open Telegram → search <strong>@BotFather</strong> → send <code>/newbot</code></li>
          <li>Follow prompts → copy the <strong>Bot Token</strong> (looks like <code>123456:ABC-DEF...</code>)</li>
          <li>Open <strong>@userinfobot</strong> → send any message → copy your <strong>Chat ID</strong></li>
          <li>For a group: add the bot to the group → use <strong>@getidsbot</strong> to get group Chat ID</li>
        </ol>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="form-label">Bot Token</label>
          <input v-model="settings.telegram_bot_token" type="text" class="form-input font-mono text-sm" placeholder="123456789:ABCdef..." />
        </div>
        <div>
          <label class="form-label">Chat ID</label>
          <input v-model="settings.telegram_chat_id" type="text" class="form-input font-mono text-sm" placeholder="-1001234567890 or 123456789" />
        </div>
      </div>

      <div class="flex gap-3">
        <button @click="saveSettings" class="btn-primary text-sm">Save Settings</button>
        <button @click="testAlert('telegram')" class="btn-secondary text-sm flex items-center gap-2">
          <ZapIcon class="w-4 h-4" /> Send Test Message
        </button>
      </div>
    </div>

    <!-- Tab: Email -->
    <div v-if="tab === 'email'" class="card p-6 space-y-5">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
          <MailIcon class="w-6 h-6 text-amber-600 dark:text-amber-400" />
        </div>
        <div class="flex-1">
          <h2 class="font-bold text-gray-900 dark:text-white">Email Alerts</h2>
          <p class="text-sm text-gray-500 mt-1">Receive detailed reports and alerts via email.</p>
        </div>
        <label class="flex items-center gap-2 cursor-pointer">
          <span class="text-sm text-gray-600 dark:text-gray-400">Enable</span>
          <div class="relative">
            <div @click="settings.email_enabled = !settings.email_enabled"
                 :class="settings.email_enabled ? 'bg-amber-500' : 'bg-gray-300 dark:bg-gray-600'"
                 class="w-10 h-5 rounded-full cursor-pointer transition-colors"></div>
            <div :class="settings.email_enabled ? 'translate-x-5' : 'translate-x-0.5'"
                 class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform pointer-events-none"></div>
          </div>
        </label>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
          <label class="form-label">Alert Email Address</label>
          <input v-model="settings.alert_email" type="email" class="form-input" placeholder="alerts@yourcompany.com" />
        </div>
        <div>
          <label class="form-label">SMTP Host (optional override)</label>
          <input v-model="settings.smtp_host" type="text" class="form-input" placeholder="smtp.gmail.com" />
        </div>
        <div>
          <label class="form-label">SMTP Port</label>
          <input v-model="settings.smtp_port" type="number" class="form-input" placeholder="587" />
        </div>
        <div>
          <label class="form-label">SMTP Username</label>
          <input v-model="settings.smtp_username" type="email" class="form-input" placeholder="your@gmail.com" />
        </div>
        <div>
          <label class="form-label">SMTP Password / App Password</label>
          <input v-model="settings.smtp_password" type="password" class="form-input" />
        </div>
        <div>
          <label class="form-label">From Name</label>
          <input v-model="settings.smtp_from_name" type="text" class="form-input" placeholder="OpenVyapar ERP" />
        </div>
      </div>

      <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-400">
        <strong>Gmail tip:</strong> Use an App Password (not your regular password). Enable 2FA → Google Account → Security → App Passwords → Generate one for "Mail".
      </div>

      <div class="flex gap-3">
        <button @click="saveSettings" class="btn-primary text-sm">Save Settings</button>
        <button @click="testAlert('email')" class="btn-secondary text-sm flex items-center gap-2">
          <ZapIcon class="w-4 h-4" /> Send Test Email
        </button>
      </div>
    </div>

    <!-- Tab: Alert Rules -->
    <div v-if="tab === 'rules'" class="space-y-4">
      <div class="flex justify-end">
        <button @click="showRuleModal = true" class="btn-primary text-sm flex items-center gap-2">
          <PlusIcon class="w-4 h-4" /> Add Rule
        </button>
      </div>

      <!-- Default quick rules -->
      <div class="card p-5">
        <h3 class="font-bold text-gray-800 dark:text-white mb-4">Quick Alert Templates</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div v-for="tpl in templates" :key="tpl.event"
               class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 dark:border-gray-800">
            <div :class="tpl.color" class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0">
              <component :is="tpl.icon" class="w-5 h-5 text-white" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-gray-800 dark:text-white text-sm">{{ tpl.name }}</p>
              <p class="text-xs text-gray-500">{{ tpl.desc }}</p>
            </div>
            <button @click="addTemplate(tpl)" class="text-xs px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 font-semibold transition">
              + Add
            </button>
          </div>
        </div>
      </div>

      <!-- Existing rules -->
      <div v-if="rules.length" class="card overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800/50">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Rule</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Event</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-400">Telegram</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-400">Email</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-400">Active</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rules" :key="r.id" class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/30">
              <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">{{ r.name }}</td>
              <td class="px-4 py-3"><span class="badge-blue text-xs">{{ r.event }}</span></td>
              <td class="px-4 py-3 text-center">{{ r.via_telegram ? '✅' : '—' }}</td>
              <td class="px-4 py-3 text-center">{{ r.via_email ? '✅' : '—' }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="r.is_active ? 'badge-green' : 'badge-gray'">{{ r.is_active ? 'On' : 'Off' }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <button @click="deleteRule(r.id)" class="text-gray-400 hover:text-red-500 transition-colors">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="card p-8 text-center text-gray-400">No alert rules yet. Add one above.</div>
    </div>

    <!-- Add Rule Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showRuleModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="showRuleModal = false">
          <div class="card w-full max-w-md p-6 space-y-4">
            <h3 class="font-bold text-gray-900 dark:text-white">New Alert Rule</h3>
            <div>
              <label class="form-label">Rule Name</label>
              <input v-model="newRule.name" class="form-input" placeholder="Low Stock Alert" />
            </div>
            <div>
              <label class="form-label">Event</label>
              <select v-model="newRule.event" class="form-input">
                <option v-for="e in eventOptions" :key="e.value" :value="e.value">{{ e.label }}</option>
              </select>
            </div>
            <div class="flex gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="newRule.via_telegram" class="rounded" />
                <span class="text-sm text-gray-700 dark:text-gray-300">Via Telegram</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="newRule.via_email" class="rounded" />
                <span class="text-sm text-gray-700 dark:text-gray-300">Via Email</span>
              </label>
            </div>
            <div class="flex gap-2 pt-2">
              <button @click="saveRule" class="btn-primary text-sm flex-1">Save Rule</button>
              <button @click="showRuleModal = false" class="btn-secondary text-sm">Cancel</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../api/client'
import {
  SendIcon, MailIcon, PackageIcon, AlertCircleIcon, ZapIcon, PlusIcon,
  TrashIcon, BellIcon, ShoppingCartIcon, CreditCardIcon, CalendarIcon,
} from 'lucide-vue-next'

const tab = ref('telegram')
const tabs = [
  { key: 'telegram', label: 'Telegram' },
  { key: 'email',    label: 'Email' },
  { key: 'rules',    label: 'Alert Rules' },
]

const flash     = ref('')
const flashType = ref<'ok'|'err'>('ok')
const checking  = ref(false)
const rules     = ref<any[]>([])
const settings  = ref<any>({ telegram_enabled: false, email_enabled: false })
const showRuleModal = ref(false)
const newRule   = ref({ name: '', event: 'low_stock', via_telegram: true, via_email: false, is_active: true })

const eventOptions = [
  { value: 'low_stock',       label: 'Low Stock Warning' },
  { value: 'overdue_payment', label: 'Overdue Payment' },
  { value: 'daily_summary',   label: 'Daily Summary' },
  { value: 'new_sale',        label: 'New Sale Invoice' },
  { value: 'new_purchase',    label: 'New Purchase Invoice' },
  { value: 'backup_done',     label: 'Backup Completed' },
  { value: 'credit_note',     label: 'Credit Note Created' },
  { value: 'new_user',        label: 'New User Added' },
]

const templates = [
  { event: 'low_stock',       name: 'Low Stock Alert',       desc: 'Alert when any product is below minimum quantity',    icon: PackageIcon,     color: 'bg-rose-500' },
  { event: 'overdue_payment', name: 'Overdue Payment',       desc: 'Alert when customer payment is past due date',         icon: AlertCircleIcon, color: 'bg-amber-500' },
  { event: 'new_sale',        name: 'New Sale',              desc: 'Alert on every new sales invoice created',             icon: ShoppingCartIcon, color: 'bg-emerald-500' },
  { event: 'new_purchase',    name: 'New Purchase',          desc: 'Alert on every purchase bill',                         icon: CreditCardIcon,   color: 'bg-blue-500' },
  { event: 'daily_summary',   name: 'Daily Summary',        desc: 'Daily sales & purchase summary',                       icon: CalendarIcon,    color: 'bg-violet-500' },
  { event: 'backup_done',     name: 'Backup Notification',  desc: 'Alert when an automatic backup completes',             icon: BellIcon,        color: 'bg-gray-500' },
]

function showFlash(msg: string, type: 'ok'|'err' = 'ok') {
  flash.value = msg; flashType.value = type
  setTimeout(() => flash.value = '', 4000)
}

async function loadSettings() {
  const { data } = await api.get('/alerts/settings')
  settings.value = data || {}
}

async function loadRules() {
  const { data } = await api.get('/alerts/rules')
  rules.value = data || []
}

async function saveSettings() {
  await api.post('/alerts/settings', settings.value)
  showFlash('Settings saved successfully!')
}

async function testAlert(channel: string) {
  try {
    const { data } = await api.post('/alerts/test', { channel })
    showFlash(data.message, 'ok')
  } catch (e: any) {
    showFlash(e.response?.data?.message || 'Test failed.', 'err')
  }
}

async function saveRule() {
  await api.post('/alerts/rules', newRule.value)
  showRuleModal.value = false
  newRule.value = { name: '', event: 'low_stock', via_telegram: true, via_email: false, is_active: true }
  loadRules()
  showFlash('Alert rule added!')
}

async function deleteRule(id: number) {
  await api.delete(`/alerts/rules/${id}`)
  loadRules()
}

function addTemplate(tpl: any) {
  newRule.value = { name: tpl.name, event: tpl.event, via_telegram: true, via_email: false, is_active: true }
  showRuleModal.value = true
}

async function runStockCheck() {
  checking.value = true
  try {
    const { data } = await api.post('/alerts/run/stock')
    showFlash(data.message, 'ok')
  } catch (e: any) {
    showFlash(e.response?.data?.message || 'Failed.', 'err')
  } finally { checking.value = false }
}

async function runOverdueCheck() {
  checking.value = true
  try {
    const { data } = await api.post('/alerts/run/overdue')
    showFlash(data.message, 'ok')
  } catch (e: any) {
    showFlash(e.response?.data?.message || 'Failed.', 'err')
  } finally { checking.value = false }
}

onMounted(() => { loadSettings(); loadRules() })
</script>
