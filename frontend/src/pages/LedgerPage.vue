<template>
  <div class="space-y-5 animate-fade-up">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Party Ledger</h1>
        <p class="text-sm text-gray-500 mt-0.5">Credit/debit statement, payments, overdue alerts</p>
      </div>
      <div class="flex gap-2 flex-wrap">
        <button @click="sendCreditAlert" class="btn-secondary text-sm flex items-center gap-2">
          <BellIcon class="w-4 h-4 text-amber-500" /> Send Credit Alert
        </button>
        <button v-if="partyId && ledger" @click="showPayModal = true" class="btn-primary text-sm flex items-center gap-2">
          <PlusIcon class="w-4 h-4" /> Record Payment
        </button>
      </div>
    </div>

    <div v-if="flash" class="p-3 rounded-xl text-sm font-semibold"
         :class="flashOk ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'">{{ flash }}</div>

    <div class="flex gap-3 flex-wrap items-center">
      <div class="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl">
        <button @click="switchType('customer')" :class="partyType==='customer'?'bg-white dark:bg-gray-900 text-blue-700 shadow-sm':'text-gray-500'"
                class="px-4 py-1.5 rounded-lg text-sm font-bold transition-all flex items-center gap-1.5">
          <UsersIcon class="w-4 h-4" /> Customers
        </button>
        <button @click="switchType('supplier')" :class="partyType==='supplier'?'bg-white dark:bg-gray-900 text-violet-700 shadow-sm':'text-gray-500'"
                class="px-4 py-1.5 rounded-lg text-sm font-bold transition-all flex items-center gap-1.5">
          <TruckIcon class="w-4 h-4" /> Suppliers
        </button>
      </div>
      <input v-model="partySearch" type="text" placeholder="Search party..." class="form-input text-sm flex-1 min-w-40 max-w-60" />
      <select v-model="partyId" @change="loadLedger" class="form-input text-sm w-56">
        <option value="">-- Select Party --</option>
        <option v-for="p in filteredParties" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
    </div>

    <div v-if="!partyId" class="space-y-4">
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="card p-4 text-center border-t-4 border-blue-500">
          <p class="text-2xl font-extrabold text-blue-600">{{ summaryList.length }}</p>
          <p class="text-xs text-gray-500 mt-0.5">Parties</p>
        </div>
        <div class="card p-4 text-center border-t-4 border-rose-500">
          <p class="text-2xl font-extrabold text-rose-600">{{ fmtNum(totalOutstanding) }}</p>
          <p class="text-xs text-gray-500 mt-0.5">Outstanding</p>
        </div>
        <div class="card p-4 text-center border-t-4 border-amber-500">
          <p class="text-2xl font-extrabold text-amber-600">{{ overdueCount }}</p>
          <p class="text-xs text-gray-500 mt-0.5">Overdue</p>
        </div>
        <div class="card p-4 text-center border-t-4 border-emerald-500">
          <p class="text-2xl font-extrabold text-emerald-600">{{ summaryList.filter(s=>s.balance<=0).length }}</p>
          <p class="text-xs text-gray-500 mt-0.5">Cleared</p>
        </div>
      </div>
      <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
          <p class="font-bold text-gray-800 dark:text-white text-sm">Outstanding List</p>
          <button @click="exportSummary" class="btn-secondary text-xs flex items-center gap-1.5"><DownloadIcon class="w-3 h-3" /> Export</button>
        </div>
        <div v-if="summaryLoading" class="p-8 text-center text-gray-400 text-sm">Loading...</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
            <tr>
              <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Party</th>
              <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Phone</th>
              <th class="px-4 py-2 text-right font-semibold text-gray-600 dark:text-gray-400">Billed</th>
              <th class="px-4 py-2 text-right font-semibold text-gray-600 dark:text-gray-400">Paid</th>
              <th class="px-4 py-2 text-right font-semibold text-gray-600 dark:text-gray-400">Balance</th>
              <th v-if="partyType==='customer'" class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400">Credit</th>
              <th class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400">Overdue</th>
              <th class="px-4 py-2 w-8"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in summaryList" :key="s.id" class="border-t border-gray-50 dark:border-gray-800/50 hover:bg-gray-50 dark:hover:bg-gray-800/30 cursor-pointer" @click="partyId=s.id;loadLedger()">
              <td class="px-4 py-2 font-medium text-gray-800 dark:text-white">{{ s.name }}</td>
              <td class="px-4 py-2 text-gray-500 text-xs">{{ s.phone || '--' }}</td>
              <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ fmtNum(s.total_invoiced ?? s.total_bills) }}</td>
              <td class="px-4 py-2 text-right text-emerald-600">{{ fmtNum(s.total_paid) }}</td>
              <td class="px-4 py-2 text-right font-bold" :class="s.balance>0?'text-rose-600':'text-emerald-600'">
                {{ fmtNum(Math.abs(s.balance)) }} {{ s.balance>0?'Dr':s.balance<0?'Cr':'' }}
              </td>
              <td v-if="partyType==='customer'" class="px-4 py-2 text-center">
                <span v-if="s.credit_status==='exceeded'" class="px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-700">Exceeded</span>
                <span v-else-if="s.credit_status==='warning'" class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700">Warning</span>
                <span v-else-if="s.credit_status==='ok'" class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">OK</span>
                <span v-else class="text-gray-300 text-xs">--</span>
              </td>
              <td class="px-4 py-2 text-center">
                <span v-if="s.overdue_count>0" class="px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-700">{{ s.overdue_count }}</span>
                <span v-else class="text-gray-300 text-xs">--</span>
              </td>
              <td class="px-4 py-2"><ChevronRightIcon class="w-4 h-4 text-gray-300" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="partyId && ledger" class="space-y-4">
      <button @click="partyId='';ledger=null" class="flex items-center gap-2 text-sm text-gray-500 hover:text-blue-600 transition-colors">
        <ChevronLeftIcon class="w-4 h-4" /> Back to list
      </button>

      <div class="card p-5" :class="partyType==='customer'?'border-l-4 border-blue-500':'border-l-4 border-violet-500'">
        <div class="flex flex-wrap items-start gap-4">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">{{ ledger.party.name }}</h2>
              <span v-if="ledger.party.gstin" class="font-mono text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 px-2 py-0.5 rounded">{{ ledger.party.gstin }}</span>
            </div>
            <div class="flex gap-4 text-sm text-gray-500 flex-wrap">
              <span v-if="ledger.party.phone">{{ ledger.party.phone }}</span>
              <span v-if="ledger.party.email">{{ ledger.party.email }}</span>
            </div>
            <div v-if="partyType==='customer' && ledger.party.credit_limit" class="mt-3 flex items-center gap-3 flex-wrap">
              <span class="text-xs text-gray-500">Limit: <strong>{{ fmtNum(ledger.party.credit_limit) }}</strong></span>
              <span class="text-xs text-gray-500">Used: <strong class="text-rose-600">{{ fmtNum(ledger.credit_used) }}</strong></span>
              <span class="text-xs text-gray-500">Free: <strong class="text-emerald-600">{{ fmtNum(ledger.credit_available) }}</strong></span>
              <div class="w-32 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div class="h-2 rounded-full" :style="'width:'+Math.min(100,(ledger.credit_used/ledger.party.credit_limit)*100)+'%'"
                     :class="ledger.credit_used>=ledger.party.credit_limit?'bg-rose-500':ledger.credit_used>=ledger.party.credit_limit*0.8?'bg-amber-500':'bg-emerald-500'"></div>
              </div>
            </div>
          </div>
          <div class="text-right flex-shrink-0">
            <div class="px-5 py-3 rounded-2xl" :class="ledger.balance>0?'bg-rose-50 dark:bg-rose-900/20':'bg-emerald-50 dark:bg-emerald-900/20'">
              <p class="text-xs font-semibold mb-1" :class="ledger.balance>0?'text-rose-500':'text-emerald-500'">
                {{ partyType==='customer'?(ledger.balance>0?'Customer Owes You':'You Owe Customer'):(ledger.balance>0?'You Owe Supplier':'Supplier Owes You') }}
              </p>
              <p class="text-2xl font-extrabold" :class="ledger.balance>0?'text-rose-600 dark:text-rose-400':'text-emerald-600 dark:text-emerald-400'">
                {{ fmtNum(Math.abs(ledger.balance)) }}
              </p>
            </div>
            <div v-if="ledger.advance_balance>0" class="mt-2 text-xs text-violet-600 font-semibold">Advance: {{ fmtNum(ledger.advance_balance) }}</div>
          </div>
        </div>
        <div v-if="ledger.overdue && ledger.overdue.length" class="mt-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200">
          <p class="text-sm font-bold text-rose-700 mb-1">{{ ledger.overdue.length }} Overdue — {{ fmtNum(ledger.overdue_amount) }}</p>
          <div class="flex flex-wrap gap-2">
            <span v-for="ov in ledger.overdue" :key="ov.invoice_number" class="text-xs bg-rose-100 text-rose-700 px-2 py-0.5 rounded-full font-mono">
              {{ ov.invoice_number }} {{ fmtNum(ov.balance_amount) }} Due {{ String(ov.due_date).slice(0,10) }}
            </span>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap gap-3 items-center">
        <div class="flex gap-3">
          <div class="px-3 py-2 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-100">
            <p class="text-xs text-rose-500 font-semibold">Total Debit</p>
            <p class="font-extrabold text-rose-700 text-sm">{{ fmtNum(ledger.total_debit) }}</p>
          </div>
          <div class="px-3 py-2 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100">
            <p class="text-xs text-emerald-500 font-semibold">Total Credit</p>
            <p class="font-extrabold text-emerald-700 text-sm">{{ fmtNum(ledger.total_credit) }}</p>
          </div>
        </div>
        <div class="flex gap-2 flex-1 justify-end flex-wrap">
          <input v-model="stmtFrom" @change="loadLedger" type="date" class="form-input text-xs w-36" />
          <input v-model="stmtTo" @change="loadLedger" type="date" class="form-input text-xs w-36" />
          <button @click="stmtFrom='';stmtTo='';loadLedger()" class="btn-secondary text-xs">All Time</button>
          <button @click="setCurrentFY" class="btn-secondary text-xs">FY {{ fyLabel }}</button>
          <button @click="exportStatement" class="btn-secondary text-xs flex items-center gap-1"><DownloadIcon class="w-3 h-3" /> Excel</button>
          <button @click="window.print()" class="btn-secondary text-xs flex items-center gap-1"><PrinterIcon class="w-3 h-3" /> Print</button>
        </div>
      </div>

      <div class="card overflow-x-auto">
        <table class="w-full text-sm min-w-[700px]">
          <thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
            <tr>
              <th class="px-4 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-400 w-28">Date</th>
              <th class="px-4 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-400 w-28">Reference</th>
              <th class="px-4 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-400">Description</th>
              <th class="px-4 py-2.5 text-center font-semibold text-gray-600 dark:text-gray-400 w-20">Mode</th>
              <th class="px-4 py-2.5 text-right font-semibold text-rose-500 w-28">Debit (Dr)</th>
              <th class="px-4 py-2.5 text-right font-semibold text-emerald-600 w-28">Credit (Cr)</th>
              <th class="px-4 py-2.5 text-right font-semibold text-gray-600 dark:text-gray-400 w-28">Balance</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(e,idx) in ledger.entries" :key="idx" :class="rowBg(e.type)" class="border-t border-gray-50 dark:border-gray-800/30">
              <td class="px-4 py-2.5 text-gray-500 text-xs font-mono">{{ String(e.date).slice(0,10) }}</td>
              <td class="px-4 py-2.5 text-xs font-mono text-blue-600 dark:text-blue-400">{{ e.ref }}</td>
              <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                {{ e.description }}
                <span v-if="e.reference" class="ml-1 text-xs text-gray-400">| {{ e.reference }}</span>
                <span v-if="e.notes" class="ml-1 text-xs text-gray-400 italic">{{ e.notes }}</span>
                <span v-if="e.status" :class="statusBadge(e.status)" class="ml-1.5 px-1.5 py-0.5 rounded-full text-xs font-bold">{{ e.status }}</span>
              </td>
              <td class="px-4 py-2.5 text-center">
                <span v-if="e.mode" :class="modeBadge(e.mode)" class="px-1.5 py-0.5 rounded-full text-xs font-bold uppercase">{{ e.mode }}</span>
              </td>
              <td class="px-4 py-2.5 text-right font-semibold" :class="e.debit>0?'text-rose-600':'text-gray-300'">{{ e.debit>0?fmtNum(e.debit):'--' }}</td>
              <td class="px-4 py-2.5 text-right font-semibold" :class="e.credit>0?'text-emerald-600':'text-gray-300'">{{ e.credit>0?fmtNum(e.credit):'--' }}</td>
              <td class="px-4 py-2.5 text-right font-bold text-xs" :class="e.balance>0?'text-rose-600':e.balance<0?'text-emerald-600':'text-gray-400'">
                {{ fmtNum(Math.abs(e.balance)) }} {{ e.balance>0?'Dr':e.balance<0?'Cr':'' }}
              </td>
            </tr>
          </tbody>
          <tfoot class="bg-gray-100 dark:bg-gray-800/60 border-t-2 border-gray-200 dark:border-gray-700">
            <tr>
              <td colspan="4" class="px-4 py-3 font-bold text-gray-700 dark:text-gray-300 text-sm">Closing Balance</td>
              <td class="px-4 py-3 text-right font-extrabold text-rose-600">{{ fmtNum(ledger.total_debit) }}</td>
              <td class="px-4 py-3 text-right font-extrabold text-emerald-600">{{ fmtNum(ledger.total_credit) }}</td>
              <td class="px-4 py-3 text-right font-extrabold text-sm" :class="ledger.balance>0?'text-rose-600':'text-emerald-600'">
                {{ fmtNum(Math.abs(ledger.balance)) }} {{ ledger.balance>0?'Dr':'Cr' }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showPayModal && ledger" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="showPayModal=false">
          <div class="card w-full max-w-lg p-6 space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="font-bold text-gray-900 dark:text-white">Record Payment</h3>
              <button @click="showPayModal=false" class="text-gray-400 hover:text-gray-700 text-xl leading-none">x</button>
            </div>
            <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800">
              <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">{{ ledger.party.name.charAt(0) }}</div>
              <div>
                <p class="font-semibold text-gray-800 dark:text-white text-sm">{{ ledger.party.name }}</p>
                <p class="text-xs text-gray-500">Balance: <span class="font-bold" :class="ledger.balance>0?'text-rose-600':'text-emerald-600'">{{ fmtNum(Math.abs(ledger.balance)) }}</span></p>
              </div>
            </div>
            <div>
              <label class="form-label">Payment Type</label>
              <div class="flex gap-2 flex-wrap">
                <button v-for="t in paymentTypes" :key="t.value" @click="pay.type=t.value"
                        :class="pay.type===t.value?t.active:'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'"
                        class="px-3 py-1.5 rounded-xl text-sm font-semibold transition-all">{{ t.label }}</button>
              </div>
            </div>
            <div>
              <label class="form-label">Amount</label>
              <input v-model.number="pay.amount" type="number" step="0.01" class="form-input text-lg font-bold" placeholder="0.00" />
              <div v-if="ledger.balance>0" class="flex gap-2 mt-2">
                <button @click="pay.amount=ledger.balance" class="text-xs px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 font-semibold">Full Due {{ fmtNum(ledger.balance) }}</button>
                <button @click="pay.amount=Math.round(ledger.balance/2)" class="text-xs px-2.5 py-1 rounded-lg bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 font-semibold">Half {{ fmtNum(Math.round(ledger.balance/2)) }}</button>
              </div>
            </div>
            <div>
              <label class="form-label">Payment Mode</label>
              <div class="grid grid-cols-5 gap-1.5">
                <button v-for="m in payModes" :key="m.value" @click="pay.mode=m.value"
                        :class="pay.mode===m.value?'bg-blue-600 text-white ring-2 ring-blue-400':'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200'"
                        class="py-2 rounded-xl text-xs font-bold transition-all text-center">{{ m.label }}</button>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div><label class="form-label">Payment Date</label><input v-model="pay.payment_date" type="date" class="form-input" /></div>
              <div><label class="form-label">Reference / UTR</label><input v-model="pay.reference" type="text" class="form-input" placeholder="Txn ID, Cheque #..." /></div>
            </div>
            <div><label class="form-label">Notes</label><input v-model="pay.notes" type="text" class="form-input" placeholder="Remarks..." /></div>
            <div v-if="pendingInvoices.length">
              <label class="form-label">Link to Invoice (auto-updates invoice balance)</label>
              <select v-model="pay.invoice_id" class="form-input text-sm">
                <option value="">-- Not linked --</option>
                <option v-for="inv in pendingInvoices" :key="inv.id" :value="inv.id">{{ inv.ref }} | Due {{ fmtNum(inv.balance_due) }}</option>
              </select>
            </div>
            <div class="flex gap-2 pt-1">
              <button @click="recordPayment" :disabled="!pay.amount||saving" class="btn-primary flex-1 text-sm disabled:opacity-50 flex items-center justify-center gap-2">
                <CheckIcon class="w-4 h-4" /> {{ saving ? 'Saving...' : 'Record Payment' }}
              </button>
              <button @click="showPayModal=false" class="btn-secondary text-sm">Cancel</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import * as XLSX from 'xlsx'
import api from '../api/client'
import { UsersIcon, TruckIcon, PlusIcon, BellIcon, DownloadIcon, PrinterIcon, ChevronRightIcon, ChevronLeftIcon, CheckIcon } from 'lucide-vue-next'

const partyType = ref<'customer'|'supplier'>('customer')
const partySearch = ref('')
const partyId = ref<any>('')
const parties = ref<any[]>([])
const summaryList = ref<any[]>([])
const summaryLoading = ref(false)
const ledger = ref<any>(null)
const stmtFrom = ref('')
const stmtTo = ref('')
const flash = ref('')
const flashOk = ref(true)
const showPayModal = ref(false)
const saving = ref(false)
const pay = ref({ type: 'received', amount: 0 as number, mode: 'cash', payment_date: new Date().toISOString().slice(0,10), reference: '', notes: '', invoice_id: '' })

const paymentTypes = [
  { value: 'received', label: 'Received', active: 'bg-emerald-600 text-white' },
  { value: 'paid',     label: 'Paid Out', active: 'bg-blue-600 text-white' },
  { value: 'advance',  label: 'Advance',  active: 'bg-violet-600 text-white' },
]
const payModes = [
  { value: 'cash', label: 'Cash' }, { value: 'upi', label: 'UPI' }, { value: 'bank', label: 'Bank' },
  { value: 'cheque', label: 'Cheque' }, { value: 'neft', label: 'NEFT' }, { value: 'rtgs', label: 'RTGS' },
  { value: 'imps', label: 'IMPS' }, { value: 'online', label: 'Online' }, { value: 'other', label: 'Other' },
]

const totalOutstanding = computed(() => summaryList.value.reduce((s:number,i:any)=>s+(i.balance||0),0))
const overdueCount = computed(() => summaryList.value.filter((i:any)=>i.overdue_count>0).length)
const filteredParties = computed(() => parties.value.filter((p:any)=>p.name.toLowerCase().includes(partySearch.value.toLowerCase())))
const pendingInvoices = computed(() => ledger.value?.entries?.filter((e:any)=>e.type==='invoice'&&e.balance_due>0)??[])
const fyLabel = computed(() => { const n=new Date();const y=n.getMonth()>=3?n.getFullYear():n.getFullYear()-1;return y+'-'+String(y+1).slice(-2) })

function showFlash(msg:string,ok=true){flash.value=msg;flashOk.value=ok;setTimeout(()=>flash.value='',4000)}
function fmtNum(n:number){return new Intl.NumberFormat('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n??0)}
function setCurrentFY(){const n=new Date();const y=n.getMonth()>=3?n.getFullYear():n.getFullYear()-1;stmtFrom.value=y+'-04-01';stmtTo.value=(y+1)+'-03-31';loadLedger()}
function switchType(t:'customer'|'supplier'){partyType.value=t;partyId.value='';ledger.value=null;loadParties();loadSummary()}

async function loadParties(){
  const {data}=await api.get(partyType.value==='customer'?'/customers':'/suppliers',{params:{per_page:500}})
  parties.value=data.data??data
}
async function loadSummary(){
  summaryLoading.value=true
  try{const {data}=await api.get(partyType.value==='customer'?'/ledger/customers':'/ledger/suppliers');summaryList.value=partyType.value==='customer'?data.customers:data.suppliers}
  finally{summaryLoading.value=false}
}
async function loadLedger(){
  if(!partyId.value)return
  const url=partyType.value==='customer'?`/ledger/customers/${partyId.value}`:`/ledger/suppliers/${partyId.value}`
  const {data}=await api.get(url,{params:{from:stmtFrom.value,to:stmtTo.value}})
  ledger.value=data
}
async function recordPayment(){
  if(!pay.value.amount)return
  saving.value=true
  try{
    await api.post('/ledger/payment',{party_type:partyType.value,party_id:partyId.value,amount:pay.value.amount,mode:pay.value.mode,type:pay.value.type,payment_date:pay.value.payment_date,reference:pay.value.reference,notes:pay.value.notes,invoice_id:pay.value.invoice_id||null})
    showPayModal.value=false
    pay.value={type:'received',amount:0,mode:'cash',payment_date:new Date().toISOString().slice(0,10),reference:'',notes:'',invoice_id:''}
    showFlash('Payment recorded!')
    loadLedger();loadSummary()
  }catch(e:any){showFlash(e.response?.data?.message||'Failed.',false)}finally{saving.value=false}
}
async function sendCreditAlert(){
  try{const {data}=await api.post('/ledger/credit-due-alert');showFlash(data.message)}
  catch(e:any){showFlash(e.response?.data?.message||'Alert failed. Configure Telegram/Email first.',false)}
}
function exportStatement(){
  if(!ledger.value)return
  const rows=ledger.value.entries.map((e:any)=>({Date:String(e.date).slice(0,10),Reference:e.ref,Description:e.description,Mode:e.mode||'','Debit(Dr)':e.debit||'','Credit(Cr)':e.credit||'',Balance:e.balance,Notes:e.notes||''}))
  rows.push({Date:'',Reference:'TOTAL',Description:'Closing Balance',Mode:'','Debit(Dr)':ledger.value.total_debit,'Credit(Cr)':ledger.value.total_credit,Balance:ledger.value.balance,Notes:''})
  const wb=XLSX.utils.book_new();XLSX.utils.book_append_sheet(wb,XLSX.utils.json_to_sheet(rows),'Ledger')
  XLSX.writeFile(wb,'Ledger_'+ledger.value.party.name+'_'+new Date().toISOString().slice(0,10)+'.xlsx')
}
function exportSummary(){
  const rows=summaryList.value.map((s:any)=>({Name:s.name,Phone:s.phone||'',Billed:s.total_invoiced??s.total_bills,Paid:s.total_paid,Balance:s.balance,Overdue:s.overdue_count}))
  const wb=XLSX.utils.book_new();XLSX.utils.book_append_sheet(wb,XLSX.utils.json_to_sheet(rows),'Outstanding')
  XLSX.writeFile(wb,partyType.value+'_outstanding.xlsx')
}
function rowBg(type:string){if(type==='opening')return'bg-gray-50/50';if(type==='credit_note'||type==='advance')return'bg-violet-50/50 dark:bg-violet-900/10';if(type==='payment')return'bg-emerald-50/30 dark:bg-emerald-900/10';return''}
function modeBadge(mode:string){const m:Record<string,string>={cash:'bg-emerald-100 text-emerald-700',upi:'bg-violet-100 text-violet-700',bank:'bg-blue-100 text-blue-700',cheque:'bg-amber-100 text-amber-700',neft:'bg-blue-100 text-blue-700',rtgs:'bg-blue-100 text-blue-700',online:'bg-cyan-100 text-cyan-700'};return m[mode]??'bg-gray-100 text-gray-600'}
function statusBadge(status:string){if(status==='paid')return'bg-emerald-100 text-emerald-700';if(status==='partially_paid')return'bg-amber-100 text-amber-700';if(status==='confirmed')return'bg-blue-100 text-blue-700';return'bg-gray-100 text-gray-500'}

onMounted(()=>{loadParties();loadSummary()})
</script>