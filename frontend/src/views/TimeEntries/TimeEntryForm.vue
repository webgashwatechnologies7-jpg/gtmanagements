<template>
  <div class="time-entry-form">
    <div class="header">
      <h1>{{ isEdit ? 'Edit Time Entry' : 'Create Time Entry' }}</h1>
      <router-link to="/time-entries" class="btn-back">Back to Time Entries</router-link>
    </div>

    <form @submit.prevent="handleSubmit" v-if="!loading">
      <div class="form-grid">
        <div class="form-group">
          <label>Date *</label>
          <input type="date" v-model="form.date" required />
        </div>

        <div class="form-group">
          <label>Project *</label>
          <select v-model="form.project_id" required>
            <option value="">Select Project</option>
            <option v-for="project in availableProjects" :key="project.id" :value="project.id">
              {{ project.name }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label>Minutes *</label>
          <input type="number" v-model.number="form.minutes" min="1" required />
          <small>{{ (form.minutes / 60).toFixed(2) }} hours</small>
        </div>

        <div class="form-group">
          <label>Entry Type</label>
          <select v-model="form.entry_type">
            <option value="regular">Regular</option>
            <option value="overtime">Overtime</option>
          </select>
        </div>

        <div class="form-group full-width">
          <label>Description</label>
          <textarea v-model="form.description" rows="3"></textarea>
        </div>
      </div>

      <div v-if="error" class="error-message">{{ error }}</div>

      <div class="form-actions">
        <button type="submit" class="btn-primary" :disabled="submitting">
          {{ submitting ? 'Saving...' : (isEdit ? 'Update Entry' : 'Create Entry') }}
        </button>
        <router-link to="/time-entries" class="btn-cancel">Cancel</router-link>
      </div>
    </form>

    <div v-if="loading" class="loading">Loading entry data...</div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { timeEntryService } from '../../services/timeEntryService'
import { projectService } from '../../services/projectService'
import { logger } from '../../utils/logger'

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => !!route.params.id)

const form = ref({
  date: new Date().toISOString().split('T')[0],
  project_id: null,
  minutes: 0,
  entry_type: 'regular',
  description: ''
})

const availableProjects = ref([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')

const loadProjects = async () => {
  try {
    const response = await projectService.getAll({ per_page: 100, status: 'active' })
    if (response.data.success) {
      availableProjects.value = response.data.data
    }
  } catch (err) {
    logger.error('Failed to load projects:', err)
  }
}

const loadEntry = async () => {
  if (!isEdit.value) return

  loading.value = true
  try {
    const response = await timeEntryService.getById(route.params.id)
    if (response.data.success) {
      const entry = response.data.data
      form.value = {
        date: entry.date,
        project_id: entry.project_id,
        minutes: entry.minutes,
        entry_type: entry.entry_type,
        description: entry.description || ''
      }
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load entry'
  } finally {
    loading.value = false
  }
}

const handleSubmit = async () => {
  submitting.value = true
  error.value = ''

  try {
    if (isEdit.value) {
      await timeEntryService.update(route.params.id, form.value)
    } else {
      await timeEntryService.create(form.value)
    }

    router.push('/time-entries')
  } catch (err) {
    if (err.response?.data?.errors) {
      const errors = err.response.data.errors
      const firstError = Object.values(errors)[0]
      error.value = Array.isArray(firstError) ? firstError[0] : firstError
    } else {
      error.value = err.response?.data?.message || 'Failed to save entry'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadProjects()
  loadEntry()
})
</script>

<style scoped>
.time-entry-form {
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

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  margin-bottom: 8px;
  font-weight: 500;
  color: #555;
}

.form-group input,
.form-group select,
.form-group textarea {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
}

.form-group small {
  margin-top: 4px;
  color: #666;
  font-size: 12px;
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
