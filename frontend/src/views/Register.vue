<template>
  <div class="register-container">
    <div class="register-card">
      <h1>GTmanagement System</h1>
      <h2>Register</h2>
      
      <form @submit.prevent="handleRegister" v-if="!loading">
        <div class="form-group">
          <label for="name">Full Name</label>
          <input
            type="text"
            id="name"
            v-model="form.name"
            required
            placeholder="Enter your full name"
          />
        </div>

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
          <label for="employee_id">Employee ID</label>
          <input
            type="text"
            id="employee_id"
            v-model="form.employee_id"
            required
            placeholder="Enter your employee ID"
          />
        </div>

        <div class="form-group">
          <label for="phone">Phone (Optional)</label>
          <input
            type="text"
            id="phone"
            v-model="form.phone"
            placeholder="Enter your phone number"
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
            minlength="8"
          />
        </div>

        <div class="form-group">
          <label for="password_confirmation">Confirm Password</label>
          <input
            type="password"
            id="password_confirmation"
            v-model="form.password_confirmation"
            required
            placeholder="Confirm your password"
          />
        </div>

        <div v-if="error" class="error-message">
          {{ error }}
        </div>

        <button type="submit" class="btn-primary">Register</button>
      </form>

      <div v-if="loading" class="loading">Registering...</div>

      <div class="login-link">
        Already have an account? <router-link to="/login">Login</router-link>
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
  name: '',
  email: '',
  employee_id: '',
  phone: '',
  password: '',
  password_confirmation: ''
})

const loading = ref(false)
const error = ref('')

async function handleRegister() {
  if (form.value.password !== form.value.password_confirmation) {
    error.value = 'Passwords do not match'
    return
  }

  loading.value = true
  error.value = ''
  // Sanitize text inputs for XSS prevention (Phase 15); do not modify password fields
  const payload = {
    name: sanitizeInput(form.value.name),
    email: sanitizeInput(form.value.email),
    employee_id: sanitizeInput(form.value.employee_id),
    phone: sanitizeInput(form.value.phone) || undefined,
    password: form.value.password,
    password_confirmation: form.value.password_confirmation
  }

  try {
    await authStore.register(payload)
    router.push('/')
  } catch (err) {
    if (err.errors) {
      error.value = Object.values(err.errors).flat().join(', ')
    } else {
      error.value = err.message || 'Registration failed. Please try again.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.register-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  background: linear-gradient(135deg, #404040 0%, #265b99 100%);
  padding: 20px;
}

.register-card {
  background: white;
  border-radius: 10px;
  padding: 40px;
  width: 100%;
  max-width: 450px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

h1 {
  text-align: center;
  color: #265b99;
  margin-bottom: 10px;
  font-size: 1.8rem;
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
  box-sizing: border-box;
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

.login-link {
  text-align: center;
  margin-top: 20px;
  color: #666;
}

.login-link a {
  color: #265b99;
  text-decoration: none;
  font-weight: 500;
}

.login-link a:hover {
  text-decoration: underline;
}
</style>
