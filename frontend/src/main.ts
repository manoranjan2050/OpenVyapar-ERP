import { createApp } from 'vue'
import { createPinia } from 'pinia'
import VueApexCharts from 'vue3-apexcharts'
import './style.css'
import App from './App.vue'
import router from './router'

import { useThemeStore } from './stores/theme'

const pinia = createPinia()
const app = createApp(App)
app.use(pinia)
app.use(router)
app.use(VueApexCharts)

// Apply saved theme before mount to avoid flash
useThemeStore(pinia).current  // access triggers applyTheme in store init

app.mount('#app')
