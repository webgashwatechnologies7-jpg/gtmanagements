<template>
  <div class="team-form">
    <div class="header">
      <h1>{{ isEdit ? 'Edit Department' : 'Create Department' }}</h1>
      <router-link to="/teams" class="btn-back">Back to Teams</router-link>
    </div>

    <form @submit.prevent="handleSubmit" v-if="!loading">
      <div class="form-grid">
        <div class="form-group">
          <label>Department Name *</label>
          <input type="text" v-model="form.name" required />
        </div>

        <div class="form-group">
          <label>Status</label>
          <select v-model="form.status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>

      <div v-if="error" class="error-message">{{ error }}</div>

      <div class="form-actions">
        <button type="submit" class="btn-primary" :disabled="submitting">
          {{ submitting ? 'Saving...' : (isEdit ? 'Update Department' : 'Create Department') }}
        </button>
        <router-link to="/teams" class="btn-cancel">Cancel</router-link>
      </div>
    </form>

    <div v-if="loading" class="loading">Loading team data...</div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { teamService } from '../../services/teamService'

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => !!route.params.id)

const form = ref({
  name: '',
  status: 'active'
})

const loading = ref(false)
const submitting = ref(false)
const error = ref('')

const loadTeam = async () => {
  if (!isEdit.value) return

  loading.value = true
  try {
    const response = await teamService.getById(route.params.id)
    if (response.data.success) {
      const team = response.data.data
      form.value.name = team.name
      form.value.status = team.status
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load team'
  } finally {
    loading.value = false
  }
}

const handleSubmit = async () => {
  submitting.value = true
  error.value = ''

  try {
    const payload = { name: form.value.name, status: form.value.status }
    if (isEdit.value) {
      await teamService.update(route.params.id, payload)
    } else {
      await teamService.create(payload)
    }

    router.push('/teams')
  } catch (err) {
    if (err.response?.data?.errors) {
      const errors = err.response.data.errors
      const firstError = Object.values(errors)[0]
      error.value = Array.isArray(firstError) ? firstError[0] : firstError
    } else {
      error.value = err.response?.data?.message || 'Failed to save team'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadTeam()
})
</script>

<style scoped>
.team-form {
  padding: 20px;
  max-width: 1000px;
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

.btn-back:hover {
  background: #5a6268;
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
  font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #1a1a1a;
}

.form-group small {
  margin-top: 4px;
  color: #666;
  font-size: 12px;
}

.member-selection {
  max-height: 300px;
  overflow-y: auto;
  border: 1px solid #ddd;
  border-radius: 5px;
  padding: 15px;
  background: #f9f9f9;
}

.checkbox-item {
  padding: 8px;
  background: white;
  margin-bottom: 8px;
  border-radius: 4px;
}

.checkbox-item label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  margin: 0;
  font-weight: normal;
}

.checkbox-item input[type="checkbox"] {
  width: auto;
  cursor: pointer;
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
  display: inline-block;
}

.btn-cancel:hover {
  background: #5a6268;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #666;
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
