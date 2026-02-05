<template>
  <div class="daily-plan-form">
    <div class="header">
      <h1>{{ isEdit ? 'Edit Daily Plan' : 'Create Daily Plan' }}</h1>
      <router-link to="/daily-plans" class="btn-back">Back to Daily Plans</router-link>
    </div>

    <form @submit.prevent="handleSubmit" v-if="!loading">
      <div class="form-group">
        <label>Date *</label>
        <input
          type="date"
          v-model="form.date"
          required
          :min="todayDate"
          :max="todayDate"
          :readonly="!!route.params.id"
        />
        <p v-if="!route.params.id" class="date-hint">Only today's date is allowed – not yesterday or tomorrow.</p>
      </div>

      <div class="form-group">
        <label>Plan Items *</label>
        <p class="assigned-tasks-hint">Select one Project per item. Use "Task create" to add a new task or "Pending task assign" to add a pending task to the plan (enter hours for both). Use "+ Add Project / Task" to add a new project. Total cannot exceed 8 hours.</p>
        <div class="total-hours" :class="{ valid: totalHours <= 8 && totalHours > 0, invalid: totalHours > 8 }">
          Total: {{ totalHours.toFixed(1) }} / 8 hours (max)
        </div>
        <div class="items-list">
          <div v-for="(item, index) in form.items" :key="index" class="item-card">
            <div class="item-header">
              <h4>Item {{ index + 1 }} – Project</h4>
              <button type="button" @click="removeItem(index)" class="btn-remove-item" v-if="form.items.length > 1">
                Remove
              </button>
            </div>
            <div class="item-fields">
              <div class="form-row">
                <div class="form-col full-width">
                  <label>Project *</label>
                  <select v-model="item.project_id" required>
                    <option value="">Select Project</option>
                    <option v-for="project in availableProjects" :key="project.id" :value="project.id">
                      {{ project.name }}
                    </option>
                  </select>
                </div>
              </div>
              <div class="tasks-in-item">
                <div v-for="(task, tIndex) in item.tasks" :key="tIndex" class="task-row">
                  <span class="task-desc">{{ task.task_name || task.description || 'Task' }}</span>
                  <span class="task-hrs">{{ task.planned_hours }}h</span>
                  <span class="task-priority">{{ task.priority }}</span>
                  <span v-if="task.task_id" class="task-badge">Assigned</span>
                  <button type="button" @click="removeTask(index, tIndex)" class="btn-remove-task">Remove</button>
                </div>
                <button type="button" @click="openTaskModal(index, 'new')" class="btn-task-create" :disabled="!item.project_id">
                  + Create task
                </button>
                <button type="button" @click="openTaskModal(index, 'existing')" class="btn-task-assign" :disabled="!item.project_id">
                  + Assign pending task
                </button>
              </div>
            </div>
          </div>
        </div>
        <button type="button" @click="addItem" class="btn-add-item">+ Add Project / Task</button>
      </div>

      <div v-if="error" class="error-message">{{ error }}</div>

      <div class="form-actions">
        <button type="submit" class="btn-primary" :disabled="submitting">
          {{ submitting ? 'Saving...' : (isEdit ? 'Update Plan' : 'Create Plan') }}
        </button>
        <router-link to="/daily-plans" class="btn-cancel">Cancel</router-link>
      </div>
    </form>

    <!-- Task create / Assign existing popup -->
    <div v-if="showTaskModal" class="modal-overlay" @click.self="closeTaskModal">
      <div class="modal-box">
        <h3>{{ taskModal.mode === 'existing' ? 'Assign pending task' : 'Create task' }}</h3>
        <template v-if="taskModal.mode === 'existing'">
          <div class="form-group">
            <label>Select pending task *</label>
            <select v-model="taskModal.selected_task_id">
              <option value="">Select task</option>
              <option v-for="t in assignedTasksForCurrentProject" :key="t.id" :value="t.id">
                {{ t.name }} ({{ t.project?.name || 'Project' }})
              </option>
            </select>
            <p v-if="assignedTasksForCurrentProject.length === 0" class="modal-hint">No pending tasks in this project. Use "Create task" for a new task.</p>
          </div>
          <div class="form-group">
            <label>Planned Hours (for this task today) *</label>
            <input type="number" v-model.number="taskModal.planned_hours" step="0.1" min="0.1" max="8" />
          </div>
        </template>
        <template v-else>
          <div class="form-group">
            <label>Task name (short) *</label>
            <input v-model="taskModal.task_name" type="text" placeholder="e.g. Login API integration" maxlength="200" />
          </div>
          <div class="form-group">
            <label>What needs to be done in this task (define clearly) *</label>
            <textarea v-model="taskModal.description" rows="4" placeholder="Describe in detail: steps, work, deliverables... (at least 20 characters)" minlength="20"></textarea>
            <p class="modal-hint">Task is created only when you describe clearly what needs to be done. Do not write a short answer.</p>
          </div>
          <div class="form-group">
            <label>Planned Hours *</label>
            <input type="number" v-model.number="taskModal.planned_hours" step="0.1" min="0.1" max="8" />
          </div>
          <div class="form-group">
            <label>Priority</label>
            <select v-model="taskModal.priority">
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
          </div>
        </template>
        <div class="modal-actions">
          <button type="button" @click="addTaskFromModal" class="btn-primary">{{ taskModal.mode === 'existing' ? 'Assign to Plan' : 'Add Task' }}</button>
          <button type="button" @click="closeTaskModal" class="btn-cancel">Cancel</button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="loading">Loading plan data...</div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { dailyPlanService } from '../../services/dailyPlanService'
import { logger } from '../../utils/logger'

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => !!route.params.id)
const todayDate = computed(() => new Date().toISOString().split('T')[0])

const form = ref({
  date: new Date().toISOString().split('T')[0],
  items: [
    { project_id: null, tasks: [] }
  ]
})

const availableProjects = ref([])
const assignedTasks = ref([])
const loading = ref(false)
const showTaskModal = ref(false)
const taskModalItemIndex = ref(null)
const taskModal = ref({
  mode: 'new',
  task_name: '',
  description: '',
  planned_hours: 1,
  priority: 'medium',
  selected_task_id: null
})

const assignedTasksForCurrentProject = computed(() => {
  const itemIndex = taskModalItemIndex.value
  if (itemIndex == null) return []
  const item = form.value.items[itemIndex]
  const pid = item?.project_id
  if (!pid) return []
  const alreadyAdded = new Set((item.tasks || []).filter(t => t.task_id).map(t => t.task_id))
  return assignedTasks.value.filter(t => t.project_id === pid && !alreadyAdded.has(t.id))
})

const totalHours = computed(() => {
  let sum = 0
  form.value.items.forEach(item => {
    (item.tasks || []).forEach(t => { sum += Number(t.planned_hours) || 0 })
  })
  return sum
})

const submitting = ref(false)
const error = ref('')

const loadProjects = async () => {
  try {
    const response = await dailyPlanService.getAssignedProjects()
    if (response.data.success && response.data.data) {
      const list = Array.isArray(response.data.data) ? response.data.data : []
      availableProjects.value = list
    }
  } catch (err) {
    logger.error('Failed to load assigned projects:', err)
    availableProjects.value = []
  }
}

const loadAssignedTasks = async () => {
  try {
    const response = await dailyPlanService.getAssignedTasks()
    if (response.data.success && response.data.data) {
      assignedTasks.value = response.data.data
    }
  } catch (err) {
    logger.error('Failed to load assigned tasks:', err)
  }
}

const loadPlan = async () => {
  if (!isEdit.value) return

  loading.value = true
  try {
    const response = await dailyPlanService.getById(route.params.id)
    if (response.data.success) {
      const plan = response.data.data
      const items = plan.items || []
      const byProject = {}
      items.forEach(item => {
        const pid = item.project_id
        if (!byProject[pid]) {
          byProject[pid] = { project_id: pid, tasks: [] }
        }
        byProject[pid].tasks.push({
          task_id: item.task_id || null,
          task_name: item.task?.name || '',
          description: item.description || '',
          planned_hours: parseFloat(item.planned_hours),
          priority: item.priority || 'medium'
        })
      })
      form.value = {
        date: plan.date,
        items: Object.values(byProject).length ? Object.values(byProject) : [{ project_id: null, tasks: [] }]
      }
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load plan'
  } finally {
    loading.value = false
  }
}

const addItem = () => {
  form.value.items.push({ project_id: null, tasks: [] })
}

const removeItem = (index) => {
  form.value.items.splice(index, 1)
}

const openTaskModal = (itemIndex, mode = 'new') => {
  taskModalItemIndex.value = itemIndex
  taskModal.value = {
    mode,
    task_name: '',
    description: '',
    planned_hours: 1,
    priority: 'medium',
    selected_task_id: null
  }
  showTaskModal.value = true
}

const closeTaskModal = () => {
  showTaskModal.value = false
  taskModalItemIndex.value = null
}

const addTaskFromModal = () => {
  const item = form.value.items[taskModalItemIndex.value]
  if (!item.tasks) item.tasks = []
  const hrs = Number(taskModal.value.planned_hours) || 0.5
  if (totalHours.value + hrs > 8) {
    error.value = 'Total cannot exceed 8 hours. Current total: ' + totalHours.value.toFixed(1) + ' + ' + hrs + ' = ' + (totalHours.value + hrs).toFixed(1) + ' hours.'
    return
  }

  if (taskModal.value.mode === 'existing') {
    const taskId = taskModal.value.selected_task_id
    if (!taskId) {
      error.value = 'Please select one pending task.'
      return
    }
    const t = assignedTasks.value.find(x => x.id === taskId)
    if (!t) {
      error.value = 'Task not found.'
      return
    }
    item.tasks.push({
      task_id: t.id,
      task_name: t.name,
      description: t.name,
      planned_hours: hrs,
      priority: t.priority || 'medium'
    })
  } else {
    const taskName = String(taskModal.value.task_name || '').trim()
    const desc = String(taskModal.value.description || '').trim()
    if (!taskName) {
      error.value = 'Please enter a short task name.'
      return
    }
    if (!desc || desc.length < 20) {
      error.value = 'Describe in detail what needs to be done in this task (at least 20 characters).'
      return
    }
    item.tasks.push({
      task_name: taskName,
      description: desc,
      planned_hours: hrs,
      priority: taskModal.value.priority || 'medium'
    })
  }
  error.value = ''
  closeTaskModal()
}

const removeTask = (itemIndex, taskIndex) => {
  form.value.items[itemIndex].tasks.splice(taskIndex, 1)
}

const handleSubmit = async () => {
  const flat = []
  form.value.items.forEach(item => {
    if (!item.project_id) return
    ;(item.tasks || []).forEach(t => {
      flat.push({
        project_id: item.project_id,
        task_id: t.task_id || null,
        task_name: t.task_name || null,
        planned_hours: Number(t.planned_hours) || 0.5,
        description: String(t.description || t.task_name || '').trim(),
        priority: t.priority || 'medium'
      })
    })
  })
  if (flat.length === 0) {
    error.value = 'Add at least one task.'
    return
  }
  if (totalHours.value > 8.01) {
    error.value = 'Total cannot exceed 8 hours. Current total: ' + totalHours.value.toFixed(1) + ' hours.'
    return
  }

  submitting.value = true
  error.value = ''

  try {
    const payload = { date: form.value.date, items: flat }
    if (isEdit.value) {
      await dailyPlanService.update(route.params.id, payload)
    } else {
      await dailyPlanService.create(payload)
    }
    router.push('/daily-plans')
  } catch (err) {
    if (err.response?.data?.errors) {
      const errors = err.response.data.errors
      const firstError = Object.values(errors)[0]
      error.value = Array.isArray(firstError) ? firstError[0] : firstError
    } else {
      error.value = err.response?.data?.message || 'Failed to save plan'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  await loadProjects()
  await loadAssignedTasks()
  if (!route.params.id) form.value.date = todayDate.value
  loadPlan()
})
</script>

<style scoped>
.daily-plan-form {
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

.form-group {
  margin-bottom: 30px;
}

.assigned-tasks-hint,
.date-hint {
  font-size: 0.9rem;
  color: #555;
  margin: 0 0 12px 0;
}

.total-hours {
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 15px;
  padding: 10px 15px;
  border-radius: 6px;
  background: #f0f0f0;
  color: #666;
}

.total-hours.valid {
  background: #d4edda;
  color: #155724;
}

.total-hours.invalid {
  background: #f8d7da;
  color: #721c24;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #555;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
}

.items-list {
  margin-top: 15px;
}

.item-card {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 15px;
  background: #f9f9f9;
}

.item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.item-header h4 {
  margin: 0;
  color: #333;
}

.btn-remove-item {
  padding: 6px 12px;
  background: #333;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.tasks-in-item {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #eee;
}

.task-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 10px;
  background: #fff;
  border-radius: 6px;
  margin-bottom: 8px;
  border: 1px solid #eee;
}

.task-desc {
  flex: 1;
  font-size: 14px;
  color: #333;
}

.task-hrs {
  font-weight: 600;
  color: #555;
}

.task-priority {
  font-size: 12px;
  text-transform: capitalize;
  color: #666;
}

.btn-remove-task {
  padding: 4px 10px;
  background: #dc3545;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.btn-task-create {
  padding: 10px 16px;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-weight: 500;
  margin-top: 8px;
}

.btn-task-create:hover:not(:disabled) {
  background: #333;
}

.btn-task-create:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-task-assign {
  padding: 10px 16px;
  background: #0d6efd;
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-weight: 500;
  margin-top: 8px;
  margin-left: 8px;
}

.btn-task-assign:hover:not(:disabled) {
  background: #0b5ed7;
}

.btn-task-assign:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.task-badge {
  font-size: 10px;
  padding: 2px 6px;
  background: #0d6efd;
  color: white;
  border-radius: 4px;
  text-transform: uppercase;
}

.modal-hint {
  font-size: 13px;
  color: #666;
  margin-top: 8px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
  margin-bottom: 15px;
}

.form-col.full-width {
  grid-column: 1 / -1;
}

.btn-add-item {
  padding: 10px 20px;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-weight: 500;
}

.btn-add-item:hover {
  background: #333;
}

.error-message {
  background: #f8d7da;
  color: #721c24;
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

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-box {
  background: white;
  padding: 24px;
  border-radius: 8px;
  min-width: 400px;
  max-width: 90vw;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.modal-box h3 {
  margin: 0 0 20px 0;
  color: #333;
}

.modal-actions {
  display: flex;
  gap: 12px;
  margin-top: 20px;
}
</style>
