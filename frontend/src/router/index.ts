import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('../pages/LoginPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: () => import('../layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/dashboard' },
        { path: 'dashboard', name: 'dashboard', component: () => import('../pages/DashboardPage.vue') },
        { path: 'products', name: 'products', component: () => import('../pages/ProductsPage.vue') },
        { path: 'products/new', name: 'products.new', component: () => import('../pages/ProductFormPage.vue') },
        { path: 'products/:id/edit', name: 'products.edit', component: () => import('../pages/ProductFormPage.vue') },
        { path: 'customers', name: 'customers', component: () => import('../pages/CustomersPage.vue') },
        { path: 'suppliers', name: 'suppliers', component: () => import('../pages/SuppliersPage.vue') },
        { path: 'sales', name: 'sales', component: () => import('../pages/SalesPage.vue') },
        { path: 'sales/new', name: 'sales.new', component: () => import('../pages/SalesInvoiceFormPage.vue') },
        { path: 'sales/:id', name: 'sales.show', component: () => import('../pages/SalesInvoiceDetailPage.vue') },
        { path: 'purchases', name: 'purchases', component: () => import('../pages/PurchasesPage.vue') },
        { path: 'purchases/new', name: 'purchases.new', component: () => import('../pages/PurchaseInvoiceFormPage.vue') },
        { path: 'inventory', name: 'inventory', component: () => import('../pages/InventoryPage.vue') },
        { path: 'reports', name: 'reports', component: () => import('../pages/ReportsPage.vue') },
        { path: 'ledger', name: 'ledger', component: () => import('../pages/LedgerPage.vue') },
        { path: 'stock-adjustments', name: 'stock-adjustments', component: () => import('../pages/StockAdjustmentPage.vue') },
        { path: 'credit-notes', name: 'credit-notes', component: () => import('../pages/CreditNotesPage.vue') },
        { path: 'users', name: 'users', component: () => import('../pages/UsersPage.vue') },
        { path: 'tally-export', name: 'tally-export', component: () => import('../pages/TallyExportPage.vue') },
        { path: 'purchases/:id', name: 'purchases.show', component: () => import('../pages/PurchaseInvoiceDetailPage.vue') },
        { path: 'settings', name: 'settings', component: () => import('../pages/SettingsPage.vue') },
        { path: 'about', name: 'about', component: () => import('../pages/AboutPage.vue') },
        { path: 'alerts', name: 'alerts', component: () => import('../pages/AlertsPage.vue') },
        { path: 'activity-log', name: 'activity-log', component: () => import('../pages/ActivityLogPage.vue') },
        { path: 'trash', name: 'trash', component: () => import('../pages/TrashPage.vue') },
        { path: 'backup', name: 'backup', component: () => import('../pages/BackupPage.vue') },
        { path: 'backup-sync', name: 'backup-sync', component: () => import('../pages/BackupSyncPage.vue') },
        { path: 'challans', name: 'challans', component: () => import('../pages/ChallanPage.vue') },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isLoggedIn) return '/login'
  if (to.meta.guest && auth.isLoggedIn) return '/'

  if (auth.isLoggedIn && !auth.user) await auth.fetchMe()
})

export default router
