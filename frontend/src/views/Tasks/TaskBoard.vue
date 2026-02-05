<template>
  <div class="task-board">
    <div class="header">
      <h1>Task Board (Kanban)</h1>
      <div class="header-actions">
        <select v-model="filterProjectId" @change="loadTasks" class="filter-select">
          <option value="">All Projects</option>
          <option v-for="p in (projects || [])" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <router-link to="/tasks" class="btn-secondary">List View</router-link>
        <router-link to="/tasks/create" class="btn-primary">Add Task</router-link>
      </div>
    </div>

    <div v-if="loading" class="loading">Loading board...</div>
    <div v-if="error" class="error">{{ error }}</div>

    <div v-else class="board">
      <div
        v-for="col in columns"
        :key="col.status"
        class="column"
      >
        <div class="column-header">
          <span class="column-title">{{ col.label }}</span>
          <span class="column-count">{{ tasksByStatus(col.status).length }}</span>
        </div>
        <div class="column-cards">
          <div
            v-for="task in tasksByStatus(col.status)"
            :key="task.id"
            class="card"
          >
            <div class="card-priority" :class="task.priority"></div>
            <h4 class="card-title">{{ task.name }}</h4>
            <p v-if="task.project" class="card-project">{{ task.project.name }}</p>
            <p v-if="task.assigned_to" class="card-assignee">👤 {{ task.assigned_to.name }}</p>
            <p v-if="task.deadline" class="card-deadline">📅 {{ task.deadline }}</p>
            <div class="card-actions">
              <select
                :value="task.status"
                @change="(e) => updateStatus(task, e.target.value)"
                class="card-status-select"
              >
                <option v-for="c in columns" :key="c.status" :value="c.status">{{ c.label }}</option>
              </select>
              <router-link :to="`/tasks/${task.id}/edit`" class="card-edit">Edit</router-link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { taskService } from '../../services/taskService'
import { projectService } from '../../services/projectService'
import { useToast } from '../../stores/toast'
import { getErrorMessage } from '../../utils/errorMessage'

const toast = useToast()
const columns = [
  { status: 'todo', label: 'To Do' },
  { status: 'in_progress', label: 'In Progress' },
  { status: 'review', label: 'Review' },
  { status: 'completed', label: 'Completed' },
  { status: 'cancelled', label: 'Cancelled' }
]

const tasks = ref([])
const projects = ref([])
const filterProjectId = ref('')
const loading = ref(false)
const error = ref('')

const tasksByStatus = (status) => {
  return tasks.value.filter(t => t.status === status)
}

const loadProjects = async () => {
  try {
    const response = await projectService.getAll({ per_page: 200 })
    if (response.data.success) projects.value = response.data.data
  } catch (_) {}
}

const loadTasks = async () => {
  loading.value = true
  error.value = ''
  try {
    const params = { per_page: 500 }
    if (filterProjectId.value) params.project_id = filterProjectId.value
    const response = await taskService.getAll(params)
    if (response.data.success) tasks.value = response.data.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load tasks'
  } finally {
    loading.value = false
  }
}

const updateStatus = async (task, status) => {
  if (task.status === status) return
  try {
    await taskService.updateStatus(task.id, status)
    task.status = status
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to update status'))
  }
}

onMounted(() => {
  loadProjects()
  loadTasks()
})
</script>

<style scoped>
.task-board {
  padding: 20px;
  min-height: 80vh;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 12px;
}

.header h1 {
  color: #333;
  font-size: 2rem;
}

.header-actions {
  display: flex;
  gap: 10px;
  align-items: center;
}

.filter-select {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
  min-width: 180px;
}

.btn-primary {
  padding: 10px 20px;
  background: #1a1a1a;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 500;
}

.btn-secondary {
  padding: 10px 20px;
  background: #6c757d;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 500;
}

.board {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  padding-bottom: 20px;
}

.column {
  flex: 0 0 280px;
  background: #f0f2f5;
  border-radius: 8px;
  padding: 12px;
  min-height: 400px;
}

.column-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  padding: 8px 0;
  border-bottom: 2px solid #ddd;
}

.column-title {
  font-weight: 600;
  color: #333;
  font-size: 15px;
}

.column-count {
  background: #1a1a1a;
  color: white;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 12px;
}

.column-cards {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.card {
  background: white;
  border-radius: 6px;
  padding: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  position: relative;
  border-left: 4px solid #ddd;
}

.card-priority {
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  border-radius: 6px 0 0 6px;
}

.card-priority.high {
  background: #1a1a1a;
}

.card-priority.medium {
  background: #555;
}

.card-priority.low {
  background: #999;
}

.card-title {
  margin: 0 0 8px 0;
  font-size: 14px;
  color: #333;
  padding-left: 4px;
}

.card-project {
  margin: 0 0 4px 0;
  font-size: 12px;
  color: #1a1a1a;
  padding-left: 4px;
}

.card-assignee,
.card-deadline {
  margin: 0 0 4px 0;
  font-size: 12px;
  color: #666;
  padding-left: 4px;
}

.card-actions {
  margin-top: 10px;
  padding-top: 8px;
  border-top: 1px solid #eee;
  display: flex;
  gap: 8px;
  align-items: center;
}

.card-status-select {
  flex: 1;
  padding: 6px 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 12px;
}

.card-edit {
  font-size: 12px;
  color: #1a1a1a;
  text-decoration: none;
}

.card-edit:hover {
  text-decoration: underline;
}

.loading, .error {
  text-align: center;
  padding: 40px;
  color: #666;
}

.error {
  color: #333;
}
</style>
