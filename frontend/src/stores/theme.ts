import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export type ThemeKey = 'blue' | 'indigo' | 'violet' | 'emerald' | 'rose' | 'orange' | 'amber' | 'cyan' | 'slate'

export interface Theme {
  key: ThemeKey
  name: string
  emoji: string
  // Tailwind color stops used in CSS vars
  c50: string; c100: string; c200: string; c300: string
  c400: string; c500: string; c600: string; c700: string; c800: string; c900: string
}

export const themes: Theme[] = [
  { key: 'blue',    name: 'Ocean Blue',   emoji: '🔵', c50:'#eff6ff', c100:'#dbeafe', c200:'#bfdbfe', c300:'#93c5fd', c400:'#60a5fa', c500:'#3b82f6', c600:'#2563eb', c700:'#1d4ed8', c800:'#1e40af', c900:'#1e3a8a' },
  { key: 'indigo',  name: 'Royal Indigo', emoji: '🟣', c50:'#eef2ff', c100:'#e0e7ff', c200:'#c7d2fe', c300:'#a5b4fc', c400:'#818cf8', c500:'#6366f1', c600:'#4f46e5', c700:'#4338ca', c800:'#3730a3', c900:'#312e81' },
  { key: 'violet',  name: 'Deep Violet',  emoji: '💜', c50:'#f5f3ff', c100:'#ede9fe', c200:'#ddd6fe', c300:'#c4b5fd', c400:'#a78bfa', c500:'#8b5cf6', c600:'#7c3aed', c700:'#6d28d9', c800:'#5b21b6', c900:'#4c1d95' },
  { key: 'emerald', name: 'Forest Green', emoji: '🟢', c50:'#ecfdf5', c100:'#d1fae5', c200:'#a7f3d0', c300:'#6ee7b7', c400:'#34d399', c500:'#10b981', c600:'#059669', c700:'#047857', c800:'#065f46', c900:'#064e3b' },
  { key: 'rose',    name: 'Cherry Rose',  emoji: '🌹', c50:'#fff1f2', c100:'#ffe4e6', c200:'#fecdd3', c300:'#fda4af', c400:'#fb7185', c500:'#f43f5e', c600:'#e11d48', c700:'#be123c', c800:'#9f1239', c900:'#881337' },
  { key: 'orange',  name: 'Sunset Orange',emoji: '🟠', c50:'#fff7ed', c100:'#ffedd5', c200:'#fed7aa', c300:'#fdba74', c400:'#fb923c', c500:'#f97316', c600:'#ea580c', c700:'#c2410c', c800:'#9a3412', c900:'#7c2d12' },
  { key: 'amber',   name: 'Golden Amber', emoji: '🟡', c50:'#fffbeb', c100:'#fef3c7', c200:'#fde68a', c300:'#fcd34d', c400:'#fbbf24', c500:'#f59e0b', c600:'#d97706', c700:'#b45309', c800:'#92400e', c900:'#78350f' },
  { key: 'cyan',    name: 'Teal Cyan',    emoji: '🩵', c50:'#ecfeff', c100:'#cffafe', c200:'#a5f3fc', c300:'#67e8f9', c400:'#22d3ee', c500:'#06b6d4', c600:'#0891b2', c700:'#0e7490', c800:'#155e75', c900:'#164e63' },
  { key: 'slate',   name: 'Cool Slate',   emoji: '⬜', c50:'#f8fafc', c100:'#f1f5f9', c200:'#e2e8f0', c300:'#cbd5e1', c400:'#94a3b8', c500:'#64748b', c600:'#475569', c700:'#334155', c800:'#1e293b', c900:'#0f172a' },
]

export const useThemeStore = defineStore('theme', () => {
  const savedKey = localStorage.getItem('ov_theme') as ThemeKey | null
  const current = ref<ThemeKey>(savedKey ?? 'blue')

  function applyTheme(key: ThemeKey) {
    const theme = themes.find(t => t.key === key) ?? themes[0]
    const root = document.documentElement

    root.style.setProperty('--p-50',  theme.c50)
    root.style.setProperty('--p-100', theme.c100)
    root.style.setProperty('--p-200', theme.c200)
    root.style.setProperty('--p-300', theme.c300)
    root.style.setProperty('--p-400', theme.c400)
    root.style.setProperty('--p-500', theme.c500)
    root.style.setProperty('--p-600', theme.c600)
    root.style.setProperty('--p-700', theme.c700)
    root.style.setProperty('--p-800', theme.c800)
    root.style.setProperty('--p-900', theme.c900)

    root.setAttribute('data-theme', key)
    localStorage.setItem('ov_theme', key)
  }

  function setTheme(key: ThemeKey) {
    current.value = key
    applyTheme(key)
  }

  // Apply on load
  applyTheme(current.value)

  return { current, themes, setTheme }
})
