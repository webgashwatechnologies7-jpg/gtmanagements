<template>
  <div class="task-form">
    <div class="header">
      <h1>{{ isEdit ? 'Edit Task' : 'Create Task' }}</h1>
      <router-link to="/tasks" class="btn-back">Back to Tasks</router-link>
    </div>

    <form @submit.prevent="handleSubmit" v-if="!loading">
      <div v-if="isEdit && taskCreator" class="task-meta">
        <span>Created by <strong>{{ taskCreator.name }}</strong> on {{ taskCreatedAt }}</span>
        <span v-if="taskTimeSince" class="time-since">{{ taskTimeSince }} ago</span>
      </div>
      <div class="form-grid">
        <div class="form-group full-width">
          <label>Task Name *</label>
          <input type="text" v-model="form.name" required />
        </div>

        <div class="form-group full-width">
          <label>Description (Is task mein kya kya karna hai)</label>
          <textarea v-model="form.description" rows="3" placeholder="Task ka scope / kya steps karne hain..."></textarea>
        </div>

        <div class="form-group">
          <label>Project *</label>
          <select v-model="form.project_id" required>
            <option value="">Select Project</option>
            <option v-for="p in (projects || [])" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>

        <!-- Employee does not assign to anyone – only creates the task -->
        <div v-if="!isEmployee" class="form-group">
          <label>Assigned To</label>
          <select v-model="form.assigned_to_user_id">
            <option value="">Unassigned</option>
            <option v-for="u in (users || [])" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
          </select>
        </div>

        <div class="form-group">
          <label>Priority</label>
          <select v-model="form.priority">
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select v-model="form.status">
            <option value="todo">Todo</option>
            <option value="in_progress">In Progress</option>
            <option value="review">Review</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <div class="form-group">
          <label>Estimated Hours (how much time this task will take)</label>
          <input type="number" v-model="form.estimated_hours" step="0.01" min="0" placeholder="e.g. 5" />
        </div>

        <div class="form-group">
          <label>Deadline (Kitna time bacha hai complete karne ke liye)</label>
          <input type="date" v-model="form.deadline" />
        </div>

        <div v-if="isEdit" class="form-group">
          <label>Actual Hours</label>
          <input type="number" v-model="form.actual_hours" step="0.01" min="0" />
        </div>

        <div v-if="isEdit" class="form-group full-width">
          <label>Completion Note (pending items / to ask from client / reminders)</label>
          <textarea v-model="form.completion_notes" rows="3" placeholder="Task complete karte waqt kuch pending ho, client se maangna ho ya yaad rakhne ke liye kuch likho..."></textarea>
        </div>
      </div>

      <div v-if="error" class="error-message">{{ error }}</div>

      <div class="form-actions">
        <button type="submit" class="btn-primary" :disabled="submitting">
          {{ submitting ? 'Saving...' : (isEdit ? 'Update Task' : 'Create Task') }}
        </button>
        <router-link to="/tasks" class="btn-cancel">Cancel</router-link>
      </div>
    </form>

    <div v-if="loading" class="loading">Loading task...</div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { taskService } from '../../services/taskService'
import { projectService } from '../../services/projectService'
import { userService } from '../../services/userService'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const isEdit = computed(() => !!route.params.id)
const isEmployee = computed(() => authStore.hasRole('employee'))

const form = ref({
  project_id: null,
  name: '',
  description: '',
  assigned_to_user_id: null,
  priority: 'medium',
  status: 'todo',
  estimated_hours: null,
  deadline: null,
  actual_hours: 0,
  completion_notes: ''
})

const projects = ref([])
const users = ref([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const loadedTask = ref(null)

const loadProjects = async () => {
  try {
    const response = await projectService.getAll({ per_page: 200 })
    if (response.data.success) {
      const data = response.data.data
      projects.value = Array.isArray(data) ? data : (data?.data ?? [])
      // URL se project_id pre-fill (e.g. project detail se "Create Task" click)
      const projectIdFromQuery = route.query.project_id
      if (!isEdit.value && projectIdFromQuery) {
        const id = Number(projectIdFromQuery)
        if (id) form.value.project_id = id
      }
    }
  } catch (_) {}
}

const loadUsers = async () => {
  try {
    const response = await userService.getAll({ per_page: 200 })
    if (response.data.success) users.value = response.data.data
  } catch (_) {}
}

const loadTask = async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    const response = await taskService.getById(route.params.id)
    if (response.data.success) {
      const t = response.data.data
      loadedTask.value = t
      form.value = {
        project_id: t.project_id,
        name: t.name,
        description: t.description || '',
        assigned_to_user_id: t.assigned_to_user_id || null,
        priority: t.priority,
        status: t.status,
        estimated_hours: t.estimated_hours ?? null,
        deadline: t.deadline || null,
        actual_hours: t.actual_hours ?? 0,
        completion_notes: t.completion_notes || ''
      }
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load task'
  } finally {
    loading.value = false
  }
}

const taskCreator = computed(() => loadedTask.value?.creator || null)
const taskCreatedAt = computed(() => {
  const d = loadedTask.value?.created_at
  if (!d) return ''
  return new Date(d).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
})
const taskTimeSince = computed(() => {
  const d = loadedTask.value?.created_at
  if (!d) return ''
  const then = new Date(d)
  const now = new Date()
  const sec = Math.floor((now - then) / 1000)
  if (sec < 60) return `${sec}s`
  const min = Math.floor(sec / 60)
  if (min < 60) return `${min}m`
  const hr = Math.floor(min / 60)
  if (hr < 24) return `${hr}h`
  const day = Math.floor(hr / 24)
  if (day < 30) return `${day}d`
  return `${Math.floor(day / 30)}mo`
})

const handleSubmit = async () => {
  submitting.value = true
  error.value = ''
  try {
    const payload = { ...form.value }
    if (payload.assigned_to_user_id === '') payload.assigned_to_user_id = null
    if (isEmployee.value && !isEdit.value) payload.assigned_to_user_id = null
    if (payload.estimated_hours === '' || payload.estimated_hours == null) delete payload.estimated_hours
    if (payload.deadline === '') payload.deadline = null
    if (!isEdit.value) delete payload.actual_hours

    if (isEdit.value) {
      await taskService.update(route.params.id, payload)
    } else {
      await taskService.create(payload)
    }
    router.push('/tasks')
  } catch (err) {
    const msg = err.response?.data?.message
    const errors = err.response?.data?.errors
    if (errors) {
      const first = Object.values(errors)[0]
      error.value = Array.isArray(first) ? first[0] : first
    } else {
      error.value = msg || 'Failed to save task'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadProjects()
  loadUsers()
  loadTask()
})
</script>

<style scoped>
.task-form {
  padding: 20px;
  max-width: 900px;
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

.task-meta {
  background: #f5f5f5;
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 20px;
  font-size: 0.95rem;
  color: #555;
}

.task-meta .time-since {
  margin-left: 12px;
  color: #666;
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
