import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../services/api'
import { logger } from '../utils/logger'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('token') || null)

  const isAuthenticated = computed(() => !!token.value)

  // Set token
  function setToken(newToken) {
    token.value = newToken
    if (newToken) {
      localStorage.setItem('token', newToken)
    } else {
      localStorage.removeItem('token')
    }
  }

  // Set user
  function setUser(userData) {
    user.value = userData
  }

  // Login
  async function login(credentials) {
    try {
      const response = await api.post('/login', credentials)
      if (response.data.success && response.data.data) {
        setToken(response.data.data.token)
        setUser(response.data.data.user)
        return response.data
      }
      throw new Error('Invalid response format')
    } catch (error) {
      // Better error handling
      if (error.response?.data?.errors) {
        const errors = error.response.data.errors
        const firstError = Object.values(errors)[0]
        throw new Error(Array.isArray(firstError) ? firstError[0] : firstError)
      }
      if (error.response?.data?.message) {
        throw new Error(error.response.data.message)
      }
      throw new Error(error.message || 'Login failed. Please try again.')
    }
  }

  // Register
  async function register(userData) {
    try {
      const response = await api.post('/register', userData)
      if (response.data.success && response.data.data) {
        setToken(response.data.data.token)
        setUser(response.data.data.user)
        return response.data
      }
      throw new Error('Invalid response format')
    } catch (error) {
      // Better error handling
      if (error.response?.data?.errors) {
        const errors = error.response.data.errors
        const firstError = Object.values(errors)[0]
        throw new Error(Array.isArray(firstError) ? firstError[0] : firstError)
      }
      if (error.response?.data?.message) {
        throw new Error(error.response.data.message)
      }
      throw new Error(error.message || 'Registration failed. Please try again.')
    }
  }

  // Logout
  async function logout() {
    try {
      await api.post('/logout')
    } catch (error) {
      logger.error('Logout error', error)
    } finally {
      setToken(null)
      setUser(null)
    }
  }

  // Get current user
  async function fetchUser() {
    try {
      const response = await api.get('/me')
      if (response.data.success && response.data.data?.user) {
        setUser(response.data.data.user)
        return response.data.data.user
      }
      throw new Error('Invalid response format')
    } catch (error) {
      setToken(null)
      setUser(null)
      throw error
    }
  }

  // Check if user has role
  function hasRole(role) {
    if (!user.value || !user.value.roles) return false
    return user.value.roles.some(r => r.slug === role)
  }

  // Check if user has any of the roles
  function hasAnyRole(roles) {
    if (!user.value || !user.value.roles) return false
    return user.value.roles.some(r => roles.includes(r.slug))
  }

  return {
    user,
    token,
    isAuthenticated,
    login,
    register,
    logout,
    fetchUser,
    hasRole,
    hasAnyRole,
    setToken,
    setUser,
  }
})
