<template>
  <div class="task-list">
    <div v-if="successMessage" class="success-message">{{ successMessage }}</div>
    <div class="header">
      <h1>Task Management</h1>
      <div class="header-actions">
        <router-link to="/tasks/board" class="btn-secondary">Kanban Board</router-link>
        <router-link to="/tasks/create" class="btn-primary">Add New Task</router-link>
      </div>
    </div>

    <!-- Stats summary -->
    <div v-if="stats" class="stats-row">
      <div class="stat-card">Total: {{ stats.total }}</div>
      <div class="stat-card todo">Todo: {{ stats.todo }}</div>
      <div class="stat-card in_progress">In Progress: {{ stats.in_progress }}</div>
      <div class="stat-card completed">Completed: {{ stats.completed }}</div>
    </div>

    <!-- Filters -->
    <div class="filters">
      <input
        type="text"
        v-model="filters.search"
        placeholder="Search tasks..."
        @input="debouncedSearch"
        class="search-input"
      />
      <select v-model="filters.project_id" @change="loadTasks" class="filter-select">
        <option value="">All Projects</option>
        <option v-for="p in (projects || [])" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filters.status" @change="loadTasks" class="filter-select">
        <option value="">All Status</option>
        <option value="todo">Todo</option>
        <option value="in_progress">In Progress</option>
        <option value="review">Review</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <select v-model="filters.priority" @change="loadTasks" class="filter-select">
        <option value="">All Priority</option>
        <option value="high">High</option>
        <option value="medium">Medium</option>
        <option value="low">Low</option>
      </select>
      <select v-model="filters.assigned_to_user_id" @change="loadTasks" class="filter-select">
        <option value="">All Assignees</option>
        <option v-for="u in (users || [])" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading tasks...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Tasks Table -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Project</th>
            <th>Priority</th>
            <th>Status</th>
            <th>View Message</th>
            <th>Est. Hours</th>
            <th>Deadline</th>
            <th>Created By</th>
            <th>Created</th>
            <th>Time since created</th>
            <th>Time left</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="task in (tasks || [])" :key="task.id">
            <td>{{ task.id }}</td>
            <td class="task-name">{{ task.name }}</td>
            <td>
              <router-link :to="`/projects/${task.project?.id}`" class="project-link" v-if="task.project">
                {{ task.project.name }}
              </router-link>
              <span v-else>-</span>
            </td>
            <td>
              <span :class="['priority-badge', task.priority]">{{ task.priority }}</span>
            </td>
            <td>
              <select
                class="status-select"
                :value="task.status"
                @change="(e) => onStatusChange(task, e)"
                :disabled="updatingStatusId === task.id"
              >
                <option value="todo">Todo</option>
                <option value="in_progress">In Progress</option>
                <option value="review">Review</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </td>
            <td>
              <button
                v-if="task.completion_notes"
                type="button"
                class="btn-view-msg"
                @click="showMessagePopup(task)"
              >
                View Message
              </button>
              <span v-else>-</span>
            </td>
            <td>{{ task.estimated_hours ?? '-' }}</td>
            <td>{{ task.deadline || '-' }}</td>
            <td>{{ task.creator?.name || '-' }}</td>
            <td>{{ task.created_at ? formatDate(task.created_at) : '-' }}</td>
            <td>{{ timeSinceCreated(task.created_at) }}</td>
            <td>{{ timeLeftToDeadline(task.deadline, task.status) }}</td>
            <td class="actions">
              <button @click="editTask(task.id)" class="btn-edit">Edit</button>
              <button v-if="!isEmployee" @click="assignTask(task)" class="btn-assign">Assign</button>
              <button @click="deleteTask(task.id)" class="btn-delete">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="(tasks || []).length === 0" class="empty-state">No tasks found.</div>

      <!-- Pagination -->
      <div v-if="meta && meta.last_page > 1" class="pagination">
        <button @click="changePage(meta.current_page - 1)" :disabled="meta.current_page === 1">
          Previous
        </button>
        <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button @click="changePage(meta.current_page + 1)" :disabled="meta.current_page === meta.last_page">
          Next
        </button>
      </div>
    </div>

    <!-- Assign modal -->
    <div v-if="showAssignModal" class="modal-overlay" @click.self="showAssignModal = false">
      <div class="modal">
        <h3>Assign Task</h3>
        <p v-if="selectedTask">Task: {{ selectedTask?.name }}</p>
        <select v-model="assignUserId" class="filter-select full-width">
          <option value="">Select user</option>
          <option v-for="u in (users || [])" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
        </select>
        <div class="modal-actions">
          <button @click="confirmAssign" class="btn-primary" :disabled="!assignUserId">Assign</button>
          <button @click="showAssignModal = false" class="btn-cancel">Cancel</button>
        </div>
      </div>
    </div>

    <!-- View Message popup -->
    <div v-if="showMessageModal" class="modal-overlay" @click.self="showMessageModal = false">
      <div class="modal modal-view-msg">
        <h3>Message</h3>
        <p v-if="messagePopupTask" class="task-name-modal">Task: {{ messagePopupTask.name }}</p>
        <div class="message-popup-text">{{ messagePopupTask?.completion_notes || '' }}</div>
        <div class="modal-actions">
          <button @click="showMessageModal = false" class="btn-cancel">Close</button>
        </div>
      </div>
    </div>

    <!-- Status change: add/update note (optional) when changing status -->
    <div v-if="showCompleteNoteModal" class="modal-overlay" @click.self="cancelCompleteNote">
      <div class="modal modal-complete-note">
        <h3>{{ pendingStatusChange === 'completed' ? 'Mark as Completed' : 'Change Status' }}</h3>
        <p v-if="taskForCompleteNote" class="task-name-modal">Task: {{ taskForCompleteNote.name }}</p>
        <p v-if="pendingStatusChange" class="status-change-to">Status: {{ formatStatus(pendingStatusChange) }}</p>
        <label class="note-label">Message / Note (optional)</label>
        <textarea
          v-model="completionNote"
          class="completion-note-textarea"
          placeholder="e.g. kuch pending hai, client se ye maangna hai, ya yaad rakhne ke liye kuch..."
          rows="4"
        ></textarea>
        <p class="note-hint">You can write anything for pending items, client requests, or reminders. It will show in "View Message".</p>
        <div class="modal-actions">
          <button @click="confirmCompleteWithNote" class="btn-primary">Save</button>
          <button @click="cancelCompleteNote" class="btn-cancel">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { taskService } from '../../services/taskService'
import { projectService } from '../../services/projectService'
import { userService } from '../../services/userService'
import { useAuthStore } from '../../stores/auth'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'

const router = useRouter()
const toast = useToast()
const confirmStore = useConfirm()
const authStore = useAuthStore()
const isEmployee = computed(() => authStore.hasRole('employee'))

const tasks = ref([])
const projects = ref([])
const users = ref([])
const stats = ref(null)
const loading = ref(false)
const error = ref('')
const filters = ref({
  search: '',
  project_id: '',
  status: '',
  priority: '',
  assigned_to_user_id: '',
  page: 1
})
const meta = ref(null)
const showAssignModal = ref(false)
const selectedTask = ref(null)
const assignUserId = ref('')
const updatingStatusId = ref(null)
const successMessage = ref('')
const showCompleteNoteModal = ref(false)
const taskForCompleteNote = ref(null)
const completionNote = ref('')
const pendingStatusChange = ref(null)
const showMessageModal = ref(false)
const messagePopupTask = ref(null)

let searchTimeout = null

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => loadTasks(), 500)
}

function formatStatus(s) {
  if (!s) return ''
  return s.replace(/_/g, ' ')
}

function formatDate(d) {
  if (!d) return '-'
  const dt = new Date(d)
  return dt.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' })
}

function timeSinceCreated(createdAt) {
  if (!createdAt) return '-'
  const then = new Date(createdAt)
  const now = new Date()
  const sec = Math.floor((now - then) / 1000)
  if (sec < 60) return `${sec}s ago`
  const min = Math.floor(sec / 60)
  if (min < 60) return `${min}m ago`
  const hr = Math.floor(min / 60)
  if (hr < 24) return `${hr}h ago`
  const day = Math.floor(hr / 24)
  if (day < 30) return `${day}d ago`
  const month = Math.floor(day / 30)
  return `${month}mo ago`
}

function timeLeftToDeadline(deadline, status) {
  if (!deadline || status === 'completed' || status === 'cancelled') return '-'
  const end = new Date(deadline)
  const now = new Date()
  if (end < now) return 'Overdue'
  const sec = Math.floor((end - now) / 1000)
  const day = Math.floor(sec / 86400)
  const hr = Math.floor((sec % 86400) / 3600)
  if (day > 0) return `${day}d ${hr}h left`
  if (hr > 0) return `${hr}h left`
  const min = Math.floor(sec / 60)
  return `${min}m left`
}

const loadTasks = async () => {
  loading.value = true
  error.value = ''
  try {
    const params = { per_page: 15, page: filters.value.page, ...filters.value }
    Object.keys(params).forEach(key => { if (params[key] === '') delete params[key] })
    const response = await taskService.getAll(params)
    if (response.data.success) {
      const data = response.data.data
      tasks.value = Array.isArray(data) ? data : (data?.data ?? [])
      meta.value = response.data.meta
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load tasks'
  } finally {
    loading.value = false
  }
}

const loadStats = async () => {
  try {
    const params = {}
    if (filters.value.project_id) params.project_id = filters.value.project_id
    const response = await taskService.getStatistics(params)
    if (response.data.success) stats.value = response.data.data
  } catch (_) {}
}

const loadProjects = async () => {
  try {
    const response = await projectService.getAll({ per_page: 200 })
    if (response.data.success) {
      const d = response.data.data
      projects.value = Array.isArray(d) ? d : (d?.data ?? [])
    }
  } catch (_) {}
}

const loadUsers = async () => {
  try {
    const response = await userService.getAll({ per_page: 200 })
    if (response.data.success) {
      const d = response.data.data
      users.value = Array.isArray(d) ? d : (d?.data ?? [])
    }
  } catch (_) {}
}

const changePage = (page) => {
  filters.value.page = page
  loadTasks()
}

const editTask = (id) => router.push(`/tasks/${id}/edit`)

const onStatusChange = (task, e) => {
  const newStatus = e.target.value
  taskForCompleteNote.value = task
  completionNote.value = task.completion_notes || ''
  pendingStatusChange.value = newStatus
  showCompleteNoteModal.value = true
  e.target.value = task.status
}

const confirmCompleteWithNote = async () => {
  if (!taskForCompleteNote.value || !pendingStatusChange.value) return
  await updateTaskStatus(taskForCompleteNote.value.id, pendingStatusChange.value, completionNote.value)
  showCompleteNoteModal.value = false
  taskForCompleteNote.value = null
  completionNote.value = ''
  pendingStatusChange.value = null
}

const cancelCompleteNote = () => {
  showCompleteNoteModal.value = false
  taskForCompleteNote.value = null
  completionNote.value = ''
  pendingStatusChange.value = null
}

const showMessagePopup = (task) => {
  messagePopupTask.value = task
  showMessageModal.value = true
}

const updateTaskStatus = async (taskId, status, completionNotes = null) => {
  if (!status) return
  updatingStatusId.value = taskId
  try {
    await taskService.updateStatus(taskId, status, completionNotes)
    await loadTasks()
    await loadStats()
    successMessage.value = 'Status updated.'
    setTimeout(() => { successMessage.value = '' }, 2000)
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to update status'))
  } finally {
    updatingStatusId.value = null
  }
}

const assignTask = (task) => {
  selectedTask.value = task
  assignUserId.value = task.assigned_to_user_id || ''
  showAssignModal.value = true
}

const confirmAssign = async () => {
  if (!selectedTask.value || !assignUserId.value) return
  try {
    await taskService.assign(selectedTask.value.id, assignUserId.value)
    showAssignModal.value = false
    loadTasks()
    loadStats()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to assign task'))
  }
}

const deleteTask = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to delete this task?')
  if (!ok) return
  try {
    await taskService.delete(id)
    loadTasks()
    loadStats()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to delete task'))
  }
}

onMounted(() => {
  loadProjects()
  loadUsers()
  loadTasks()
  loadStats()
})
</script>

<style scoped>
.task-list {
  padding: 20px;
  max-width: 1600px;
  margin: 0 auto;
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
}

.btn-primary {
  padding: 10px 20px;
  background: #1a1a1a;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 500;
  border: none;
  cursor: pointer;
}

.btn-secondary {
  padding: 10px 20px;
  background: #6c757d;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 500;
}

.btn-primary:hover,
.btn-secondary:hover {
  opacity: 0.9;
}

.stats-row {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.stat-card {
  padding: 10px 16px;
  background: #f0f0f0;
  border-radius: 6px;
  font-size: 14px;
}

.stat-card.todo { background: #e8e8e8; }
.stat-card.in_progress { background: #e0e0e0; }
.stat-card.completed { background: #d0d0d0; }

.filters {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.search-input {
  flex: 1;
  min-width: 200px;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
}

.filter-select {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
  min-width: 140px;
}

.table-container {
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table thead {
  background: #f5f5f5;
}

.data-table th {
  padding: 14px;
  text-align: left;
  font-weight: 600;
  color: #333;
  border-bottom: 2px solid #ddd;
}

.data-table td {
  padding: 14px;
  border-bottom: 1px solid #eee;
}

.task-name { font-weight: 500; }

.project-link {
  color: #1a1a1a;
  text-decoration: none;
}

.project-link:hover { text-decoration: underline; }

.priority-badge, .status-badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.priority-badge.high { background: #333; color: #fff; }
.priority-badge.medium { background: #666; color: #fff; }
.priority-badge.low { background: #999; color: #fff; }

.status-badge.todo { background: #e8e8e8; color: #333; }
.status-badge.in_progress { background: #ccc; color: #333; }
.status-badge.review { background: #bbb; color: #333; }
.status-badge.completed { background: #555; color: #fff; }
.status-badge.cancelled { background: #999; color: #fff; }

.status-select {
  padding: 6px 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 12px;
  background: white;
  min-width: 120px;
}

.success-message {
  background: #d4edda;
  border: 1px solid #c3e6cb;
  color: #155724;
  padding: 10px 12px;
  border-radius: 6px;
  margin-bottom: 15px;
}

.actions { display: flex; gap: 8px; }

.btn-edit {
  padding: 6px 12px;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.btn-assign {
  padding: 6px 12px;
  background: #555;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.btn-delete {
  padding: 6px 12px;
  background: #333;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.btn-edit:hover, .btn-assign:hover, .btn-delete:hover { opacity: 0.9; }

.empty-state {
  padding: 40px;
  text-align: center;
  color: #666;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  padding: 20px;
}

.pagination button {
  padding: 8px 16px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 4px;
  cursor: pointer;
}

.pagination button:disabled { opacity: 0.5; cursor: not-allowed; }

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  padding: 24px;
  border-radius: 8px;
  min-width: 320px;
}

.modal h3 { margin-top: 0; }
.modal .full-width { width: 100%; margin: 12px 0; }
.modal-actions { display: flex; gap: 10px; margin-top: 16px; }
.btn-cancel { padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; }

.modal-complete-note { min-width: 420px; }
.task-name-modal { font-weight: 500; margin: 8px 0 12px 0; color: #333; }
.status-change-to { font-size: 0.9rem; color: #555; margin: 0 0 12px 0; }
.note-label { display: block; font-weight: 600; margin-bottom: 6px; color: #555; }
.completion-note-textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-family: inherit; resize: vertical; margin-bottom: 6px; }
.note-hint { font-size: 0.8rem; color: #666; margin: 0 0 12px 0; }

.btn-view-msg {
  padding: 6px 12px;
  background: #265b99;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
  font-weight: 500;
}

.btn-view-msg:hover {
  background: #1e4a7a;
}

.modal-view-msg { min-width: 400px; max-width: 500px; }
.message-popup-text {
  white-space: pre-wrap;
  padding: 14px;
  background: #f5f5f5;
  border-radius: 8px;
  border: 1px solid #e0e0e0;
  margin: 12px 0 16px 0;
  max-height: 300px;
  overflow-y: auto;
  color: #333;
}

.loading, .error {
  text-align: center;
  padding: 40px;
  color: #666;
}
.error { color: #333; }
</style>
