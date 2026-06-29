import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../api/client'

interface User {
  id: number
  uuid: string
  name: string
  email: string
  company_id: number | null
  company?: { id: number; name: string; gstin?: string }
  roles: string[]
  permissions: string[]
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('token'))

  const isLoggedIn = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.roles.includes('admin') ?? false)

  async function login(email: string, password: string) {
    const { data } = await api.post('/auth/login', { email, password })
    token.value = data.token
    localStorage.setItem('token', data.token)
    user.value = { ...data.user, roles: data.roles, permissions: data.permissions }
  }

  async function fetchMe() {
    try {
      const { data } = await api.get('/auth/me')
      user.value = { ...data.user, roles: data.roles, permissions: data.permissions }
    } catch {
      logout()
    }
  }

  function logout() {
    api.post('/auth/logout').catch(() => {})
    token.value = null
    user.value = null
    localStorage.removeItem('token')
  }

  return { user, token, isLoggedIn, isAdmin, login, fetchMe, logout }
})
