<template>
  <div class="holiday-form">
    <div class="header">
      <h1>{{ isEdit ? 'Edit Holiday' : 'Create Holiday' }}</h1>
      <router-link to="/holidays" class="btn-back">Back to Holidays</router-link>
    </div>

    <form @submit.prevent="handleSubmit" v-if="!loading">
      <div class="form-grid">
        <div class="form-group">
          <label>Date *</label>
          <input type="date" v-model="form.date" required />
        </div>

        <div class="form-group">
          <label>Name *</label>
          <input type="text" v-model="form.name" required />
        </div>

        <div class="form-group">
          <label>Type</label>
          <select v-model="form.type">
            <option value="holiday">Holiday</option>
            <option value="weekend">Weekend</option>
            <option value="custom">Custom</option>
          </select>
        </div>

        <div class="form-group">
          <label>
            <input type="checkbox" v-model="form.is_active" />
            Active
          </label>
        </div>
      </div>

      <div v-if="error" class="error-message">{{ error }}</div>

      <div class="form-actions">
        <button type="submit" class="btn-primary" :disabled="submitting">
          {{ submitting ? 'Saving...' : (isEdit ? 'Update Holiday' : 'Create Holiday') }}
        </button>
        <router-link to="/holidays" class="btn-cancel">Cancel</router-link>
      </div>
    </form>

    <div v-if="loading" class="loading">Loading holiday data...</div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { holidayService } from '../../services/holidayService'

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => !!route.params.id)

const form = ref({
  date: '',
  name: '',
  type: 'holiday',
  is_active: true
})

const loading = ref(false)
const submitting = ref(false)
const error = ref('')

const loadHoliday = async () => {
  if (!isEdit.value) return

  loading.value = true
  try {
    const response = await holidayService.getById(route.params.id)
    if (response.data.success) {
      const holiday = response.data.data
      form.value = {
        date: holiday.date,
        name: holiday.name,
        type: holiday.type,
        is_active: holiday.is_active
      }
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load holiday'
  } finally {
    loading.value = false
  }
}

const handleSubmit = async () => {
  submitting.value = true
  error.value = ''

  try {
    if (isEdit.value) {
      await holidayService.update(route.params.id, form.value)
    } else {
      await holidayService.create(form.value)
    }

    router.push('/holidays')
  } catch (err) {
    if (err.response?.data?.errors) {
      const errors = err.response.data.errors
      const firstError = Object.values(errors)[0]
      error.value = Array.isArray(firstError) ? firstError[0] : firstError
    } else {
      error.value = err.response?.data?.message || 'Failed to save holiday'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadHoliday()
})
</script>

<style scoped>
.holiday-form {
  padding: 20px;
  max-width: 800px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.header h1 {
  color: #333;
  font-size: 2rem;
}

.btn-back {
  padding: 10px 20px;
  background: #6c757d;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 500;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  margin-bottom: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  margin-bottom: 8px;
  font-weight: 500;
  color: #555;
}

.form-group input[type="checkbox"] {
  width: auto;
  margin-right: 8px;
}

.form-group input,
.form-group select {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
}

.error-message {
  background: #eee;
  color: #333;
  padding: 12px;
  border-radius: 5px;
  margin-bottom: 20px;
}

.form-actions {
  display: flex;
  gap: 15px;
  margin-top: 30px;
}

.btn-primary {
  padding: 12px 24px;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 5px;
  font-size: 16px;
  font-weight: 500;
  cursor: pointer;
}

.btn-primary:hover:not(:disabled) {
  background: #333;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-cancel {
  padding: 12px 24px;
  background: #6c757d;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-size: 16px;
  font-weight: 500;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #666;
}
</style>
