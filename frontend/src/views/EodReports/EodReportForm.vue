<template>
  <div class="eod-report-form">
    <div class="header">
      <h1>{{ isEdit ? 'Edit EOD Report' : 'Create EOD Report' }}</h1>
      <router-link to="/eod-reports" class="btn-back">Back to EOD Reports</router-link>
    </div>

    <form @submit.prevent="handleSubmit" v-if="!loading">
      <div class="form-group">
        <label>Date *</label>
        <input
          type="date"
          v-model="form.date"
          required
          @change="loadPlanForDate"
          :min="todayDate"
          :max="todayDate"
          :readonly="!isEdit"
        />
        <p v-if="!isEdit" class="date-hint">Only today's date is allowed – not yesterday or tomorrow.</p>
      </div>

      <!-- Morning plan items appear here; edit and submit as EOD -->
      <div class="form-group" v-if="planItemsByProject.length > 0">
        <label>Plan Items (today's plan – edit and submit as EOD) *</label>
        <p class="assigned-tasks-hint">Items from your morning daily plan appear here. Tick done, write what you did, add notes where pending – then click Create Report.</p>
        <div class="items-list">
          <div v-for="(proj, pIdx) in planItemsByProject" :key="pIdx" class="item-card">
            <div class="item-header">
              <h4>Item {{ pIdx + 1 }} – {{ proj.project_name }}</h4>
            </div>
            <div class="tasks-in-item">
              <div v-for="(pt, tIdx) in proj.tasks" :key="tIdx" class="task-row eod-task-row">
                <input type="checkbox" v-model="pt.done" :id="'done-' + pIdx + '-' + tIdx" class="task-done-cb" />
                <span class="task-desc">{{ pt.task_name || pt.description || 'Task' }} <span class="task-hrs">{{ pt.planned_hours }}h</span></span>
                <div class="task-edit-col">
                  <input v-model="pt.work_summary" type="text" placeholder="What you did (edit here)" class="task-work-input" />
                  <textarea v-model="pt.remaining_work" rows="2" placeholder="Pending / note..." class="task-note-input"></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="form-group" v-else-if="!isEdit && form.date">
        <p class="assigned-tasks-hint">No daily plan found for this date. Use "Add Item" below to add report items.</p>
      </div>

      <!-- Report Items - same card style as Daily Plan -->
      <div class="form-group">
        <label>Report Items (extra work not in the plan) *</label>
        <div class="items-list">
          <div v-for="(item, index) in form.items" :key="index" class="item-card">
            <div class="item-header">
              <h4>Item {{ index + 1 }} – Project</h4>
              <button type="button" @click="removeItem(index)" class="btn-remove-item" v-if="form.items.length > 1">Remove</button>
            </div>
            <div class="item-fields">
              <div class="form-row">
                <div class="form-col">
                  <label>Project *</label>
                  <select v-model="item.project_id" required>
                    <option value="">Select Project</option>
                    <option v-for="project in availableProjects" :key="project.id" :value="project.id">
                      {{ project.name }}
                    </option>
                  </select>
                </div>
                <div class="form-col">
                  <label>Progress %</label>
                  <input type="number" v-model.number="item.progress_percentage" min="0" max="100" />
                </div>
              </div>
              <div class="form-row">
                <div class="form-col full-width">
                  <label>Work Summary *</label>
                  <textarea v-model="item.work_summary" rows="3" required placeholder="What work was done..."></textarea>
                </div>
              </div>
              <div class="form-row">
                <div class="form-col">
                  <label>Regular Minutes</label>
                  <input type="number" v-model.number="item.regular_minutes" min="0" />
                </div>
                <div class="form-col">
                  <label>Overtime Minutes</label>
                  <input type="number" v-model.number="item.overtime_minutes" min="0" />
                </div>
              </div>
              <div class="form-row">
                <div class="form-col">
                  <label>Status Update</label>
                  <input type="text" v-model="item.status_update" placeholder="e.g. in_progress, completed" />
                </div>
              </div>
              <div class="form-row">
                <div class="form-col full-width">
                  <label>Remaining Work</label>
                  <textarea v-model="item.remaining_work" rows="2"></textarea>
                </div>
              </div>
              <div class="form-row">
                <div class="form-col full-width">
                  <label>Blockers</label>
                  <textarea v-model="item.blockers" rows="2"></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>
        <button type="button" @click="addItem" class="btn-add-item">+ Add Project / Item</button>
      </div>

      <div v-if="error" class="error-message">{{ error }}</div>

      <div class="form-actions">
        <button type="submit" class="btn-primary" :disabled="submitting">
          {{ submitting ? 'Saving...' : (isEdit ? 'Update Report' : 'Create Report') }}
        </button>
        <router-link to="/eod-reports" class="btn-cancel">Cancel</router-link>
      </div>
    </form>

    <div v-if="loading" class="loading">Loading report data...</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { eodReportService } from '../../services/eodReportService'
import { dailyPlanService } from '../../services/dailyPlanService'
import { logger } from '../../utils/logger'

const router = useRouter()
const route = useRoute()

const isEdit = ref(!!route.params.id)
const todayDate = computed(() => new Date().toISOString().split('T')[0])

const form = ref({
  date: new Date().toISOString().split('T')[0],
  items: [
    {
      project_id: null,
      work_summary: '',
      progress_percentage: 0,
      remaining_work: '',
      blockers: '',
      regular_minutes: 0,
      overtime_minutes: 0,
      status_update: ''
    }
  ]
})

const availableProjects = ref([])
const planTasks = ref([])
const loading = ref(false)

const planItemsByProject = computed(() => {
  const byProject = {}
  planTasks.value.forEach(pt => {
    const pid = pt.project_id
    const name = pt.project_name || 'Project'
    if (!byProject[pid]) byProject[pid] = { project_id: pid, project_name: name, tasks: [] }
    byProject[pid].tasks.push(pt)
  })
  return Object.values(byProject)
})
const submitting = ref(false)
const error = ref('')

const loadProjects = async () => {
  try {
    const response = await dailyPlanService.getAssignedProjects()
    if (response.data.success && response.data.data) {
      availableProjects.value = Array.isArray(response.data.data) ? response.data.data : []
    }
  } catch (err) {
    logger.error('Failed to load projects:', err)
    availableProjects.value = []
  }
}

const loadPlanForDate = async () => {
  const date = form.value.date
  if (!date) return
  try {
    const response = await dailyPlanService.getByDate(date)
    if (response.data.success && response.data.data?.items?.length) {
      planTasks.value = response.data.data.items.map(item => ({
        task_id: item.task_id,
        project_id: item.project_id,
        project_name: item.project?.name || 'Project',
        task_name: item.task?.name || null,
        description: item.description || '',
        planned_hours: item.planned_hours ?? 0,
        done: false,
        work_summary: '',
        remaining_work: ''
      }))
    } else {
      planTasks.value = []
    }
  } catch (err) {
    planTasks.value = []
  }
}

const loadReport = async () => {
  if (!isEdit.value) return

  loading.value = true
  try {
    const response = await eodReportService.getById(route.params.id)
    if (response.data.success) {
      const report = response.data.data
      form.value = {
        date: report.date,
        items: report.items ? report.items.map(item => ({
          project_id: item.project_id,
          task_id: item.task_id || null,
          work_summary: item.work_summary || '',
          progress_percentage: item.progress_percentage || 0,
          remaining_work: item.remaining_work || '',
          blockers: item.blockers || '',
          regular_minutes: item.regular_minutes || 0,
          overtime_minutes: item.overtime_minutes || 0,
          status_update: item.status_update || ''
        })) : form.value.items
      }
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load report'
  } finally {
    loading.value = false
  }
}

const addItem = () => {
  form.value.items.push({
    project_id: null,
    work_summary: '',
    progress_percentage: 0,
    remaining_work: '',
    blockers: '',
    regular_minutes: 0,
    overtime_minutes: 0,
    status_update: ''
  })
}

const removeItem = (index) => {
  form.value.items.splice(index, 1)
}

const buildPayloadItems = () => {
  const items = []

  planTasks.value.forEach(pt => {
    const summary = String(pt.work_summary || '').trim() || (pt.done ? 'Completed' : (pt.description || 'In progress'))
    items.push({
      project_id: pt.project_id,
      task_id: pt.task_id || null,
      work_summary: summary,
      progress_percentage: pt.done ? 100 : 0,
      remaining_work: (pt.remaining_work || '').trim() || null,
      blockers: null,
      regular_minutes: 0,
      overtime_minutes: 0,
      status_update: pt.done ? 'completed' : 'in_progress'
    })
  })

  form.value.items.forEach(item => {
    if (!item.project_id || !String(item.work_summary || '').trim()) return
    items.push({
      project_id: item.project_id,
      task_id: null,
      work_summary: String(item.work_summary).trim(),
      progress_percentage: item.progress_percentage || 0,
      remaining_work: (item.remaining_work || '').trim() || null,
      blockers: (item.blockers || '').trim() || null,
      regular_minutes: item.regular_minutes || 0,
      overtime_minutes: item.overtime_minutes || 0,
      status_update: (item.status_update || '').trim() || null
    })
  })

  return items
}

const handleSubmit = async () => {
  const items = isEdit.value
    ? form.value.items
        .filter(item => item.project_id && String(item.work_summary || '').trim())
        .map(item => ({
          project_id: item.project_id,
          task_id: item.task_id || null,
          work_summary: String(item.work_summary).trim(),
          progress_percentage: item.progress_percentage || 0,
          remaining_work: (item.remaining_work || '').trim() || null,
          blockers: (item.blockers || '').trim() || null,
          regular_minutes: item.regular_minutes || 0,
          overtime_minutes: item.overtime_minutes || 0,
          status_update: (item.status_update || '').trim() || null
        }))
    : buildPayloadItems()

  if (items.length === 0) {
    error.value = 'At least one item is required: load today\'s daily plan (correct date) or add work via "Add Project / Item".'
    return
  }

  submitting.value = true
  error.value = ''

  try {
    const payload = { date: form.value.date, items }
    if (isEdit.value) {
      await eodReportService.update(route.params.id, payload)
    } else {
      await eodReportService.create(payload)
    }
    router.push('/eod-reports')
  } catch (err) {
    if (err.response?.data?.errors) {
      const errors = err.response.data.errors
      const firstError = Object.values(errors)[0]
      error.value = Array.isArray(firstError) ? firstError[0] : firstError
    } else {
      error.value = err.response?.data?.message || 'Failed to save report'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  await loadProjects()
  await loadReport()
  if (!isEdit.value) {
    form.value.date = todayDate.value
    await loadPlanForDate()
  }
})
watch(() => form.value.date, () => {
  if (!isEdit.value) loadPlanForDate()
})
</script>

<style scoped>
/* Same look as Daily Plan form */
.eod-report-form {
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

.plan-tasks-card {
  margin-bottom: 15px;
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
  align-items: flex-start;
  gap: 12px;
  padding: 8px 10px;
  background: #fff;
  border-radius: 6px;
  margin-bottom: 8px;
  border: 1px solid #eee;
}

.eod-task-row {
  align-items: center;
}

.task-done-cb {
  width: auto;
  flex-shrink: 0;
  margin-top: 2px;
}

.task-desc {
  flex: 1;
  font-size: 14px;
  color: #333;
}

.task-project-tag {
  font-size: 12px;
  color: #666;
}

.task-edit-col {
  min-width: 220px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.task-work-input {
  margin: 0;
  font-size: 13px;
  padding: 6px 8px;
}

.task-note-input {
  margin: 0;
  min-height: 44px;
  font-size: 13px;
}

.task-hrs {
  font-weight: 600;
  color: #555;
  margin-left: 4px;
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
</style>
