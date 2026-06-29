<template>
  <div class="max-w-4xl space-y-8 animate-fade-up pb-12">

    <!-- Developer Hero -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 p-8 text-white animate-fade-down">
      <div class="absolute inset-0 opacity-10"
           style="background-image:radial-gradient(circle at 20% 50%,white 1px,transparent 1px);background-size:28px 28px"></div>
      <div class="absolute -right-12 -top-12 w-56 h-56 rounded-full bg-blue-500/10 blur-3xl"></div>
      <div class="absolute -left-8 -bottom-8 w-40 h-40 rounded-full bg-violet-500/10 blur-2xl"></div>

      <div class="relative flex items-center gap-6">
        <!-- Avatar -->
        <div class="relative flex-shrink-0">
          <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center text-white text-3xl font-extrabold shadow-xl">
            MR
          </div>
          <div class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-emerald-400 border-2 border-slate-900 flex items-center justify-center">
            <div class="w-2 h-2 rounded-full bg-white"></div>
          </div>
        </div>

        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-1">
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30 uppercase tracking-wider">Developer</span>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 uppercase tracking-wider">Open Source</span>
          </div>
          <h1 class="text-2xl font-extrabold text-white">Manoranjan Kumar</h1>
          <p class="text-blue-300 mt-0.5 text-sm">Full Stack Developer · IoT Engineer · Open Source Enthusiast</p>
          <p class="text-slate-400 text-sm mt-1">Bhagalpur, Bihar, India 🇮🇳</p>

          <!-- Links -->
          <div class="flex flex-wrap items-center gap-3 mt-4">
            <a href="https://github.com/manoranjan2050" target="_blank" rel="noopener"
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold transition-all border border-white/10 hover:border-white/25">
              <GithubIcon class="w-4 h-4" /> github.com/manoranjan2050
            </a>
            <a href="mailto:electroiot.in@gmail.com"
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold transition-all border border-white/10 hover:border-white/25">
              <MailIcon class="w-4 h-4" /> electroiot.in@gmail.com
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Two column: App Info + Developer Bio -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-fade-up delay-75">

      <!-- App Info -->
      <div class="card p-6">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center">
            <span class="text-white font-extrabold text-sm">OV</span>
          </div>
          <div>
            <h2 class="font-bold text-gray-900 dark:text-white">OpenVyapar ERP</h2>
            <p class="text-xs text-gray-400">Indian GST-Ready Business Software</p>
          </div>
        </div>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-800">
            <span class="text-gray-500">Version</span>
            <span class="font-semibold text-gray-900 dark:text-white font-mono">v1.0.0</span>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-800">
            <span class="text-gray-500">Release</span>
            <span class="font-semibold text-gray-900 dark:text-white">June 2026</span>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-800">
            <span class="text-gray-500">License</span>
            <span class="font-bold text-blue-600 dark:text-blue-400">AGPL v3</span>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-800">
            <span class="text-gray-500">GST Compliance</span>
            <span class="badge-green">India FY 2025-26</span>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-800">
            <span class="text-gray-500">Backend</span>
            <span class="font-semibold text-gray-700 dark:text-gray-300">Laravel 12 · PHP 8.2</span>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-800">
            <span class="text-gray-500">Frontend</span>
            <span class="font-semibold text-gray-700 dark:text-gray-300">Vue 3 · Vite · TypeScript</span>
          </div>
          <div class="flex justify-between py-2">
            <span class="text-gray-500">Database</span>
            <span class="font-semibold text-gray-700 dark:text-gray-300">MySQL / MariaDB</span>
          </div>
        </div>
      </div>

      <!-- Developer Bio -->
      <div class="card p-6">
        <h2 class="font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
          <UserIcon class="w-4 h-4 text-blue-500" /> About the Developer
        </h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
          Manoranjan Kumar is a passionate full-stack developer and IoT engineer from Bihar, India. He
          builds open-source tools for Indian small businesses, especially those underserved by expensive
          proprietary software.
        </p>
        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mt-3">
          OpenVyapar ERP was created to give every Indian shopkeeper — from a kirana store to a wholesale
          trader — a free, modern, GST-compliant billing system that works offline and doesn't require a
          monthly subscription.
        </p>
        <div class="mt-5 grid grid-cols-2 gap-3">
          <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-center">
            <p class="text-xl font-extrabold text-blue-700 dark:text-blue-300">100%</p>
            <p class="text-xs text-blue-500 dark:text-blue-400 mt-0.5">Free & Open Source</p>
          </div>
          <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-center">
            <p class="text-xl font-extrabold text-emerald-700 dark:text-emerald-300">India</p>
            <p class="text-xs text-emerald-500 dark:text-emerald-400 mt-0.5">GST + HSN + IGST</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Tech Stack Credits -->
    <div class="card p-6 animate-fade-up delay-100">
      <h2 class="font-bold text-gray-900 dark:text-white mb-5 flex items-center gap-2">
        <CodeIcon class="w-4 h-4 text-violet-500" /> Technology Credits
      </h2>
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
        OpenVyapar ERP is built on the shoulders of these excellent open-source projects. Full credit and gratitude to their authors and contributors:
      </p>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div v-for="t in techStack" :key="t.name"
             class="flex items-start gap-3 p-3 rounded-xl border border-gray-100 dark:border-gray-800 hover:border-gray-200 dark:hover:border-gray-700 transition-all">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 text-sm font-bold" :class="t.bg">{{ t.abbr }}</div>
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ t.name }}</p>
              <span class="text-xs font-mono text-gray-400">{{ t.version }}</span>
              <span class="px-1.5 py-0.5 rounded text-xs font-bold" :class="t.licenseBg">{{ t.license }}</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-snug">{{ t.desc }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- EULA / License -->
    <div class="card p-6 animate-fade-up delay-150">
      <h2 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
        <ScaleIcon class="w-4 h-4 text-orange-500" /> License & Terms of Use
      </h2>

      <div class="space-y-4 text-sm">
        <!-- AGPL Banner -->
        <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
          <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center flex-shrink-0">
              <ScaleIcon class="w-5 h-5 text-white" />
            </div>
            <div>
              <p class="font-bold text-blue-800 dark:text-blue-200">GNU Affero General Public License v3 (AGPL-3.0)</p>
              <p class="text-blue-700 dark:text-blue-300 mt-1 text-xs leading-relaxed">
                This software is free and open source. You are free to use, study, modify, and distribute it under the terms of the AGPL v3.
                If you deploy a modified version as a network service, you must make your modified source code available to users.
              </p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
            <p class="font-bold text-emerald-700 dark:text-emerald-300 text-xs uppercase tracking-wider mb-2">You Can</p>
            <ul class="text-xs text-emerald-700 dark:text-emerald-400 space-y-1">
              <li>✓ Use commercially</li>
              <li>✓ Modify the source code</li>
              <li>✓ Distribute copies</li>
              <li>✓ Self-host for your business</li>
              <li>✓ Use privately</li>
            </ul>
          </div>
          <div class="p-3 rounded-xl bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800">
            <p class="font-bold text-orange-700 dark:text-orange-300 text-xs uppercase tracking-wider mb-2">You Must</p>
            <ul class="text-xs text-orange-700 dark:text-orange-400 space-y-1">
              <li>⚡ Disclose source code</li>
              <li>⚡ Keep the same AGPL license</li>
              <li>⚡ State all changes made</li>
              <li>⚡ Include copyright notice</li>
              <li>⚡ Share network use source</li>
            </ul>
          </div>
          <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800">
            <p class="font-bold text-rose-700 dark:text-rose-300 text-xs uppercase tracking-wider mb-2">You Cannot</p>
            <ul class="text-xs text-rose-700 dark:text-rose-400 space-y-1">
              <li>✗ Sublicense as proprietary</li>
              <li>✗ Remove copyright notices</li>
              <li>✗ Hold developer liable</li>
              <li>✗ Use developer's trademarks</li>
              <li>✗ Distribute without source</li>
            </ul>
          </div>
        </div>

        <!-- Full License Text Summary -->
        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 font-mono text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
          <p class="font-bold text-gray-800 dark:text-gray-200 mb-2">COPYRIGHT NOTICE</p>
          <p>Copyright (C) 2026 Manoranjan Kumar &lt;electroiot.in@gmail.com&gt;</p>
          <p class="mt-2">This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.</p>
          <p class="mt-2">This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.</p>
          <p class="mt-2">You should have received a copy of the GNU Affero General Public License along with this program. If not, see &lt;https://www.gnu.org/licenses/&gt;.</p>
        </div>
      </div>
    </div>

    <!-- Disclaimer -->
    <div class="card p-6 animate-fade-up delay-200">
      <h2 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
        <AlertTriangleIcon class="w-4 h-4 text-amber-500" /> Disclaimer & GST Declaration
      </h2>
      <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
        <p>
          <strong class="text-gray-800 dark:text-gray-200">No Warranty:</strong>
          OpenVyapar ERP is provided "as is" without any warranty of any kind, express or implied. The developer makes no representations about the suitability of this software for any particular purpose.
        </p>
        <p>
          <strong class="text-gray-800 dark:text-gray-200">GST Compliance:</strong>
          This software implements GST calculations as per the Indian GST Act 2017. However, it is the user's responsibility to verify all tax calculations with a qualified Chartered Accountant or GST practitioner before filing. The developer is not liable for any tax discrepancies, penalties, or losses arising from use of this software.
        </p>
        <p>
          <strong class="text-gray-800 dark:text-gray-200">Data Privacy:</strong>
          OpenVyapar ERP stores all data locally on your own server. No business data is transmitted to the developer or any third party. You are solely responsible for securing and backing up your data.
        </p>
        <p>
          <strong class="text-gray-800 dark:text-gray-200">Not a CA Tool:</strong>
          This software is a bookkeeping aid for small businesses. It does not constitute chartered accountancy, financial advisory, or legal advice. For statutory filings (GSTR-1, GSTR-3B, ITR), always work with a qualified professional.
        </p>
        <p>
          <strong class="text-gray-800 dark:text-gray-200">Limitation of Liability:</strong>
          In no event shall the developer be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including but not limited to loss of data, business interruption, or financial loss) arising from the use of this software, even if advised of the possibility of such damage.
        </p>
      </div>
    </div>

    <!-- Open Source Declaration -->
    <div class="card p-6 animate-fade-up delay-225">
      <h2 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
        <HeartIcon class="w-4 h-4 text-rose-500" /> Open Source Declaration
      </h2>
      <div class="p-4 rounded-xl bg-gradient-to-r from-rose-50 to-orange-50 dark:from-rose-900/20 dark:to-orange-900/20 border border-rose-200 dark:border-rose-800 mb-4">
        <p class="text-sm text-rose-800 dark:text-rose-300 leading-relaxed font-medium">
          OpenVyapar ERP is proudly built for the Indian small business community. It is — and will always remain — free and open source.
          No subscription. No cloud lock-in. No hidden fees. Your data stays with you.
        </p>
      </div>
      <div class="text-sm text-gray-600 dark:text-gray-400 space-y-2 leading-relaxed">
        <p>This project welcomes contributions from developers across India and the world. Whether you fix a bug, add a language translation, improve the UI, or write documentation — every contribution matters.</p>
        <p>If OpenVyapar ERP helped your business, consider:</p>
        <div class="flex flex-wrap gap-3 mt-3">
          <a href="https://github.com/manoranjan2050/openvyapar-erp" target="_blank" rel="noopener"
             class="flex items-center gap-2 btn-secondary text-sm">
            <StarIcon class="w-4 h-4 text-amber-500" /> Star on GitHub
          </a>
          <a href="https://github.com/manoranjan2050/openvyapar-erp/issues" target="_blank" rel="noopener"
             class="flex items-center gap-2 btn-secondary text-sm">
            <BugIcon class="w-4 h-4 text-blue-500" /> Report a Bug
          </a>
          <a href="https://github.com/manoranjan2050/openvyapar-erp/pulls" target="_blank" rel="noopener"
             class="flex items-center gap-2 btn-secondary text-sm">
            <GitPullRequestIcon class="w-4 h-4 text-violet-500" /> Submit a PR
          </a>
          <a href="mailto:electroiot.in@gmail.com"
             class="flex items-center gap-2 btn-secondary text-sm">
            <MailIcon class="w-4 h-4 text-emerald-500" /> Contact Developer
          </a>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="text-center py-4 animate-fade-up delay-300">
      <p class="text-xs text-gray-400 dark:text-gray-600">
        Made with ❤️ in India · OpenVyapar ERP v1.0.0 · AGPL v3 · Copyright © 2026 Manoranjan Kumar
      </p>
      <p class="text-xs text-gray-300 dark:text-gray-700 mt-1">
        "Vyapar" (व्यापार) means "Business" in Hindi — OpenVyapar = Open Business
      </p>
    </div>

  </div>
</template>

<script setup lang="ts">
import {
  GithubIcon, MailIcon, UserIcon, CodeIcon, ScaleIcon,
  AlertTriangleIcon, HeartIcon, StarIcon, BugIcon, GitPullRequestIcon,
} from 'lucide-vue-next'

const techStack = [
  { name: 'Laravel', abbr: 'L', version: '^12.0', license: 'MIT', desc: 'PHP web application framework by Taylor Otwell', bg: 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300', licenseBg: 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400' },
  { name: 'Vue.js', abbr: 'V', version: '^3.5', license: 'MIT', desc: 'Progressive JavaScript UI framework by Evan You', bg: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300', licenseBg: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' },
  { name: 'Vite', abbr: 'Vi', version: '^6.0', license: 'MIT', desc: 'Next-generation frontend build tool by Evan You', bg: 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300', licenseBg: 'bg-violet-50 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400' },
  { name: 'Tailwind CSS', abbr: 'Tw', version: '^3.4', license: 'MIT', desc: 'Utility-first CSS framework by Adam Wathan', bg: 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-300', licenseBg: 'bg-cyan-50 text-cyan-600 dark:bg-cyan-900/30 dark:text-cyan-400' },
  { name: 'TypeScript', abbr: 'TS', version: '^5.7', license: 'Apache 2.0', desc: 'Typed JavaScript superset by Microsoft', bg: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300', licenseBg: 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400' },
  { name: 'Pinia', abbr: 'Pi', version: '^2.3', license: 'MIT', desc: 'Vue state management library by Eduardo San Martin Morote', bg: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300', licenseBg: 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' },
  { name: 'Laravel Sanctum', abbr: 'Sa', version: '^4.0', license: 'MIT', desc: 'API token authentication by Taylor Otwell', bg: 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300', licenseBg: 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400' },
  { name: 'Lucide Icons', abbr: 'Lu', version: '^0.475', license: 'ISC', desc: 'Beautiful open-source icon library, fork of Feather Icons', bg: 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300', licenseBg: 'bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400' },
  { name: 'ApexCharts', abbr: 'Ax', version: '^4.7', license: 'MIT', desc: 'Modern charting library by Juned Chhipa', bg: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300', licenseBg: 'bg-orange-50 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400' },
  { name: 'SheetJS (xlsx)', abbr: 'SJ', version: '^0.20', license: 'Apache 2.0', desc: 'JavaScript spreadsheet library by SheetJS LLC', bg: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300', licenseBg: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' },
  { name: 'VueUse', abbr: 'VU', version: '^12.7', license: 'MIT', desc: 'Collection of Vue Composition utilities by Anthony Fu', bg: 'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300', licenseBg: 'bg-teal-50 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400' },
  { name: 'Axios', abbr: 'Ax', version: '^1.9', license: 'MIT', desc: 'Promise-based HTTP client by Matt Zabriskie', bg: 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300', licenseBg: 'bg-violet-50 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400' },
  { name: 'MySQL / MariaDB', abbr: 'DB', version: '8.0+ / 10.6+', license: 'GPL v2', desc: 'Open-source relational database management system', bg: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300', licenseBg: 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400' },
  { name: 'Spatie Activity Log', abbr: 'SP', version: '^4.0', license: 'MIT', desc: 'Laravel activity logging package by Spatie', bg: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300', licenseBg: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
]
</script>
