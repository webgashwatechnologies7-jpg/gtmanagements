<template>
  <div class="task-detail">
    <div class="header">
      <h1>Task Details</h1>
      <router-link to="/tasks" class="btn-back">Back to Tasks</router-link>
    </div>

    <div v-if="loading" class="loading">Loading task...</div>
    <div v-else-if="error" class="error">{{ error }}</div>

    <div v-else-if="task" class="task-info">
      <div class="info-card">
        <h2>{{ task.name }}</h2>
        <p v-if="task.description" class="description">{{ task.description }}</p>

        <div class="meta-line" v-if="task.creator">
          <span>Created by <strong>{{ task.creator.name }}</strong> on {{ formatDate(task.created_at) }}</span>
          <span v-if="task.created_at" class="time-since">{{ timeSince(task.created_at) }} ago</span>
        </div>

        <div class="info-grid">
          <div class="info-item">
            <label>Status</label>
            <span :class="['status-badge', task.status]">{{ formatStatus(task.status) }}</span>
          </div>
          <div class="info-item">
            <label>Priority</label>
            <span :class="['priority-badge', task.priority]">{{ task.priority }}</span>
          </div>
          <div class="info-item" v-if="task.project">
            <label>Project</label>
            <router-link :to="`/projects/${task.project.id}`" class="project-link">{{ task.project.name }}</router-link>
          </div>
          <div class="info-item" v-if="task.assigned_to">
            <label>Assigned To</label>
            <span>{{ task.assigned_to.name }}</span>
          </div>
          <div class="info-item">
            <label>Estimated Hours</label>
            <span>{{ task.estimated_hours ?? '-' }}</span>
          </div>
          <div class="info-item">
            <label>Actual Hours</label>
            <span>{{ task.actual_hours ?? '-' }}</span>
          </div>
          <div class="info-item">
            <label>Deadline</label>
            <span>{{ task.deadline || '-' }}</span>
          </div>
        </div>

        <div v-if="task.completion_notes" class="completion-notes-block">
          <h3>Completion Note</h3>
          <p class="completion-notes-text">{{ task.completion_notes }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { taskService } from '../../services/taskService'

const route = useRoute()
const task = ref(null)
const loading = ref(false)
const error = ref('')

const taskId = computed(() => route.params.id)

const formatDate = (d) => {
  if (!d) return '-'
  return new Date(d).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
}

const formatStatus = (s) => {
  if (!s) return ''
  return String(s).replace(/_/g, ' ')
}

const timeSince = (d) => {
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
}

const loadTask = async () => {
  if (!taskId.value) return
  loading.value = true
  error.value = ''
  try {
    const res = await taskService.getById(taskId.value)
    if (res.data.success) task.value = res.data.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load task'
  } finally {
    loading.value = false
  }
}

onMounted(() => loadTask())
</script>

<style scoped>
.task-detail {
  padding: 20px;
  max-width: 900px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.header h1 {
  font-size: 1.75rem;
  color: #333;
  margin: 0;
}

.btn-back {
  padding: 10px 20px;
  background: #6c757d;
  color: white;
  text-decoration: none;
  border-radius: 6px;
  font-weight: 500;
}

.btn-back:hover {
  background: #5a6268;
}

.loading, .error {
  text-align: center;
  padding: 40px;
  color: #666;
}

.error {
  color: #c82333;
}

.info-card {
  background: #fff;
  padding: 28px;
  border-radius: 10px;
  border: 1px solid #e0e0e0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.info-card h2 {
  margin: 0 0 10px 0;
  color: #333;
  font-size: 1.5rem;
}

.description {
  color: #555;
  margin-bottom: 16px;
  white-space: pre-wrap;
}

.meta-line {
  font-size: 0.9rem;
  color: #666;
  margin-bottom: 20px;
}

.time-since {
  margin-left: 10px;
  color: #888;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 18px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-item label {
  font-weight: 600;
  color: #555;
  font-size: 0.85rem;
}

.status-badge, .priority-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 500;
  text-transform: capitalize;
  width: fit-content;
}

.status-badge.todo { background: #e8e8e8; color: #333; }
.status-badge.in_progress { background: #cce5ff; color: #004085; }
.status-badge.review { background: #fff3cd; color: #856404; }
.status-badge.completed { background: #d4edda; color: #155724; }
.status-badge.cancelled { background: #f8d7da; color: #721c24; }

.priority-badge.high { background: #f8d7da; color: #721c24; }
.priority-badge.medium { background: #fff3cd; color: #856404; }
.priority-badge.low { background: #d4edda; color: #155724; }

.project-link {
  color: #265b99;
  text-decoration: none;
}

.project-link:hover {
  text-decoration: underline;
}

.completion-notes-block {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #eee;
}

.completion-notes-block h3 {
  margin: 0 0 10px 0;
  font-size: 1rem;
  color: #555;
}

.completion-notes-text {
  margin: 0;
  color: #333;
  white-space: pre-wrap;
  background: #f9f9f9;
  padding: 14px;
  border-radius: 8px;
  border: 1px solid #eee;
}
</style>
