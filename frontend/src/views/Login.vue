<template>
  <div class="login-container">
    <div class="login-card">
      <img src="/favicon.ico" alt="GTmanagement" class="login-logo" />
      <h2>Login</h2>
      
      <form @submit.prevent="handleLogin" v-if="!loading">
        <div class="form-group">
          <label for="email">Email</label>
          <input
            type="email"
            id="email"
            v-model="form.email"
            required
            placeholder="Enter your email"
          />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input
            type="password"
            id="password"
            v-model="form.password"
            required
            placeholder="Enter your password"
          />
        </div>

        <div v-if="error" class="error-message">
          {{ error }}
        </div>

        <button type="submit" class="btn-primary">Login</button>
      </form>

      <div v-if="loading" class="loading">Logging in...</div>

      <div class="register-link">
        Don't have an account? <router-link to="/register">Register</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { sanitizeInput } from '../utils/sanitize'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  email: '',
  password: ''
})

const loading = ref(false)
const error = ref('')

async function handleLogin() {
  loading.value = true
  error.value = ''
  // Sanitize email to prevent XSS (Phase 15)
  const payload = { email: sanitizeInput(form.value.email), password: form.value.password }

  try {
    await authStore.login(payload)
    router.push('/')
  } catch (err) {
    error.value = err.message || 'Login failed. Please check your credentials.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  background: linear-gradient(135deg, #404040 0%, #265b99 100%);
  padding: 20px;
}

.login-card {
  background: white;
  border-radius: 10px;
  padding: 40px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.login-logo {
  display: block;
  margin: 0 auto 24px;
  height: 40px;
  width: auto;
}

h2 {
  text-align: center;
  color: #333;
  margin-bottom: 30px;
  font-size: 1.5rem;
}

.form-group {
  margin-bottom: 20px;
}

label {
  display: block;
  margin-bottom: 8px;
  color: #555;
  font-weight: 500;
}

input {
  width: 100%;
  padding: 12px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 16px;
  transition: border-color 0.3s;
}

input:focus {
  outline: none;
  border-color: #265b99;
}

.error-message {
  background: #eee;
  color: #333;
  padding: 10px;
  border-radius: 5px;
  margin-bottom: 15px;
  font-size: 14px;
}

.btn-primary {
  width: 100%;
  padding: 12px;
  background: #404040;
  color: white;
  border: none;
  border-radius: 5px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.3s;
}

.btn-primary:hover {
  background: #265b99;
}

.loading {
  text-align: center;
  color: #265b99;
  padding: 20px;
}

.register-link {
  text-align: center;
  margin-top: 20px;
  color: #666;
}

.register-link a {
  color: #265b99;
  text-decoration: none;
  font-weight: 500;
}

.register-link a:hover {
  text-decoration: underline;
}
</style>
