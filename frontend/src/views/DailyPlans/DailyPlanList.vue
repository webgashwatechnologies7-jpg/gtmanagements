<template>
  <div class="daily-plan-list">
    <div class="header">
      <h1>Daily Plans (Morning Reports)</h1>
      <router-link to="/daily-plans/create" class="btn-primary">Create Daily Plan</router-link>
    </div>

    <!-- Filters -->
    <div class="filters">
      <select v-if="isAdmin" v-model="filters.user_id" @change="loadPlans" class="filter-select member-select">
        <option value="">All Members</option>
        <option v-for="u in membersList" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
      </select>
      <input
        type="date"
        v-model="filters.date"
        @change="loadPlans"
        class="filter-input"
      />
      <select v-model="filters.status" @change="loadPlans" class="filter-select">
        <option value="">All Status</option>
        <option value="draft">Draft</option>
        <option value="submitted">Submitted</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading daily plans...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Plans Table -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th v-if="isAdmin">Member</th>
            <th>Date</th>
            <th>Items</th>
            <th>Status</th>
            <th>Submitted At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="plan in (plans || [])" :key="plan.id">
            <td v-if="isAdmin">{{ plan.user?.name || '-' }} <span v-if="plan.user?.email" class="member-email">({{ plan.user.email }})</span></td>
            <td>{{ plan.date }}</td>
            <td>{{ plan.items?.length || 0 }} item(s)</td>
            <td>
              <span :class="['status-badge', plan.status]">
                {{ plan.status }}
              </span>
            </td>
            <td>{{ plan.submitted_at || '-' }}</td>
            <td class="actions">
              <button @click="viewPlan(plan.id)" class="btn-view">View</button>
              <button @click="openSendEod(plan)" class="btn-send-eod">Send EOD</button>
              <button v-if="plan.status === 'draft'" @click="editPlan(plan.id)" class="btn-edit">Edit</button>
              <button v-if="plan.status === 'draft'" @click="submitPlan(plan.id)" class="btn-submit">Submit</button>
              <button @click="deletePlan(plan.id)" class="btn-delete">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>

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

    <!-- Send EOD popup -->
    <div v-if="sendEodModal.show" class="modal-overlay" @click.self="closeSendEod">
      <div class="modal-box send-eod-modal">
        <h3>Send EOD – {{ sendEodModal.plan?.date }}</h3>
        <p class="modal-hint">Select which tasks are done and which are pending. You can add notes and hours for each task.</p>
        <div v-if="sendEodModal.loading" class="modal-loading">Loading plan...</div>
        <template v-else>
          <div class="eod-task-list">
            <div v-for="(t, idx) in sendEodModal.tasks" :key="idx" class="eod-task-row">
              <input type="checkbox" v-model="t.done" :id="'eod-done-' + idx" />
              <div class="eod-task-main">
                <label :for="'eod-done-' + idx" class="eod-task-name">{{ t.task_name || t.description || 'Task' }}</label>
                <span class="eod-task-project">({{ t.project_name }})</span>
                <textarea v-model="t.note" rows="2" placeholder="Note (if you want to add anything)" class="eod-task-note"></textarea>
                <div class="eod-task-hours">
                  <label>Regular (min)</label>
                  <input type="number" v-model.number="t.regular_minutes" min="0" placeholder="0" />
                  <label>OT (min)</label>
                  <input type="number" v-model.number="t.overtime_minutes" min="0" placeholder="0" />
                </div>
              </div>
            </div>
          </div>
          <!-- Add work on other projects here -->
          <div class="eod-extra-section">
            <h4 class="eod-extra-title">Work on another project? (Add to EOD)</h4>
            <div class="eod-extra-form">
              <div class="eod-extra-row">
                <label>Project *</label>
                <select v-model="sendEodModal.extra.project_id" class="eod-extra-select">
                  <option value="">Select project</option>
                  <option v-for="p in sendEodModal.availableProjects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
              </div>
              <div class="eod-extra-row">
                <label>What work was done (work summary) *</label>
                <input type="text" v-model="sendEodModal.extra.work_summary" placeholder="Short description" class="eod-extra-input" />
              </div>
              <div class="eod-extra-row eod-extra-hours">
                <label>Regular (min)</label>
                <input type="number" v-model.number="sendEodModal.extra.regular_minutes" min="0" placeholder="0" />
                <label>OT (min)</label>
                <input type="number" v-model.number="sendEodModal.extra.overtime_minutes" min="0" placeholder="0" />
              </div>
              <button type="button" @click="addExtraItem" class="btn-add-extra">+ Add to EOD</button>
            </div>
            <div v-if="sendEodModal.extraItems.length" class="eod-extra-list">
              <div v-for="(ex, exIdx) in sendEodModal.extraItems" :key="exIdx" class="eod-extra-item">
                <span class="ex-project">{{ ex.project_name }}</span>
                <span class="ex-summary">{{ ex.work_summary }}</span>
                <span class="ex-hrs">{{ ex.regular_minutes || 0 }}m + {{ ex.overtime_minutes || 0 }}m OT</span>
                <button type="button" @click="removeExtraItem(exIdx)" class="btn-remove-extra">Remove</button>
              </div>
            </div>
          </div>
        </template>
        <div v-if="sendEodModal.error" class="modal-error">{{ sendEodModal.error }}</div>
        <div class="modal-actions">
          <button type="button" @click="submitSendEod" class="btn-primary" :disabled="sendEodModal.sending">
            {{ sendEodModal.sending ? 'Sending...' : 'Send EOD' }}
          </button>
          <button type="button" @click="closeSendEod" class="btn-cancel">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { dailyPlanService } from '../../services/dailyPlanService'
import { eodReportService } from '../../services/eodReportService'
import { projectService } from '../../services/projectService'
import { userService } from '../../services/userService'
import { useAuthStore } from '../../stores/auth'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'

const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()
const confirmStore = useConfirm()
const isAdmin = computed(() => authStore.hasRole('admin'))

const plans = ref([])
const loading = ref(false)
const error = ref('')
const filters = ref({
  user_id: '',
  date: '',
  status: ''
})
const meta = ref(null)
const membersList = ref([])

const loadPlans = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      per_page: 15,
      date: filters.value.date || undefined,
      status: filters.value.status || undefined
    }
    if (isAdmin.value && filters.value.user_id) {
      params.user_id = filters.value.user_id
    }
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === undefined) delete params[key]
    })

    const response = await dailyPlanService.getAll(params)
    if (response.data.success) {
      plans.value = response.data.data
      meta.value = response.data.meta
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load daily plans'
  } finally {
    loading.value = false
  }
}

const loadMembers = async () => {
  if (!isAdmin.value) return
  try {
    const res = await userService.getAll({ per_page: 300 })
    const data = res.data?.data
    membersList.value = Array.isArray(data) ? data : (data?.data ?? [])
  } catch {
    membersList.value = []
  }
}

const changePage = (page) => {
  filters.value.page = page
  loadPlans()
}

const viewPlan = (id) => {
  router.push(`/daily-plans/${id}`)
}

const editPlan = (id) => {
  router.push(`/daily-plans/${id}/edit`)
}

const submitPlan = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to submit this daily plan?')
  if (!ok) return
  try {
    await dailyPlanService.submit(id)
    loadPlans()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to submit plan'))
  }
}

const deletePlan = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to delete this daily plan?')
  if (!ok) return
  try {
    await dailyPlanService.delete(id)
    loadPlans()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to delete plan'))
  }
}

const sendEodModal = ref({
  show: false,
  plan: null,
  tasks: [],
  extraItems: [],
  availableProjects: [],
  extra: { project_id: '', work_summary: '', regular_minutes: 0, overtime_minutes: 0 },
  loading: false,
  sending: false,
  error: ''
})

const openSendEod = async (plan) => {
  sendEodModal.value = {
    show: true,
    plan,
    tasks: [],
    extraItems: [],
    availableProjects: [],
    extra: { project_id: '', work_summary: '', regular_minutes: 0, overtime_minutes: 0 },
    loading: true,
    sending: false,
    error: ''
  }
  try {
    const [planRes, projectsRes] = await Promise.all([
      dailyPlanService.getById(plan.id),
      projectService.getAll({ per_page: 200 })
    ])
    if (projectsRes.data.success && Array.isArray(projectsRes.data.data)) {
      sendEodModal.value.availableProjects = projectsRes.data.data
    }
    if (planRes.data.success && planRes.data.data?.items?.length) {
      sendEodModal.value.tasks = planRes.data.data.items.map(item => ({
        task_id: item.task_id,
        project_id: item.project_id,
        project_name: item.project?.name || 'Project',
        task_name: item.task?.name || null,
        description: item.description || '',
        done: false,
        note: '',
        regular_minutes: 0,
        overtime_minutes: 0
      }))
    } else {
      sendEodModal.value.error = ''
      sendEodModal.value.tasks = []
    }
  } catch (err) {
    sendEodModal.value.error = err.response?.data?.message || 'Failed to load plan.'
  } finally {
    sendEodModal.value.loading = false
  }
}

const addExtraItem = () => {
  const { extra, availableProjects, extraItems } = sendEodModal.value
  if (!extra.project_id || !(extra.work_summary || '').trim()) {
    sendEodModal.value.error = 'Project and work summary are required.'
    return
  }
  const proj = availableProjects.find(p => p.id === parseInt(extra.project_id))
  extraItems.push({
    project_id: parseInt(extra.project_id),
    project_name: proj?.name || 'Project',
    work_summary: (extra.work_summary || '').trim(),
    regular_minutes: extra.regular_minutes || 0,
    overtime_minutes: extra.overtime_minutes || 0
  })
  sendEodModal.value.extra = { project_id: '', work_summary: '', regular_minutes: 0, overtime_minutes: 0 }
  sendEodModal.value.error = ''
}

const removeExtraItem = (idx) => {
  sendEodModal.value.extraItems.splice(idx, 1)
}

const closeSendEod = () => {
  sendEodModal.value = {
    show: false,
    plan: null,
    tasks: [],
    extraItems: [],
    availableProjects: [],
    extra: { project_id: '', work_summary: '', regular_minutes: 0, overtime_minutes: 0 },
    loading: false,
    sending: false,
    error: ''
  }
}

const submitSendEod = async () => {
  const { plan, tasks, extraItems } = sendEodModal.value
  if (!plan) return
  const planItems = (tasks || []).map(t => ({
    project_id: t.project_id,
    task_id: t.task_id || null,
    work_summary: t.done ? 'Completed' : (t.description || 'In progress'),
    progress_percentage: t.done ? 100 : 0,
    remaining_work: (t.note || '').trim() || null,
    blockers: null,
    regular_minutes: t.regular_minutes || 0,
    overtime_minutes: t.overtime_minutes || 0,
    status_update: t.done ? 'completed' : 'in_progress'
  }))
  const extraEodItems = (extraItems || []).map(ex => ({
    project_id: ex.project_id,
    task_id: null,
    work_summary: ex.work_summary,
    progress_percentage: 100,
    remaining_work: null,
    blockers: null,
    regular_minutes: ex.regular_minutes || 0,
    overtime_minutes: ex.overtime_minutes || 0,
    status_update: 'completed'
  }))
  const items = [...planItems, ...extraEodItems]
  if (!items.length) {
    sendEodModal.value.error = 'Kam se kam ek item chahiye (plan tasks ya aur project add karo).'
    return
  }

  sendEodModal.value.sending = true
  sendEodModal.value.error = ''

  try {
    await eodReportService.create({ date: plan.date, items })
    closeSendEod()
    loadPlans()
  } catch (err) {
    sendEodModal.value.error = err.response?.data?.message || 'Failed to send EOD.'
  } finally {
    sendEodModal.value.sending = false
  }
}

onMounted(() => {
  loadMembers()
  loadPlans()
})
</script>

<style scoped>
.daily-plan-list {
  padding: 20px;
  max-width: 1400px;
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

.btn-primary {
  padding: 10px 20px;
  background: #1a1a1a;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 500;
}

.btn-primary:hover {
  background: #333;
}

.filters {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
}

.filter-input,
.filter-select,
.member-select {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
}

.member-email {
  font-size: 0.85rem;
  color: #666;
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
  padding: 15px;
  text-align: left;
  font-weight: 600;
  color: #333;
  border-bottom: 2px solid #ddd;
}

.data-table td {
  padding: 15px;
  border-bottom: 1px solid #eee;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.status-badge.draft {
  background: #fff3cd;
  color: #333;
}

.status-badge.submitted {
  background: #e0e0e0;
  color: #333;
}

.actions {
  display: flex;
  gap: 10px;
}

.btn-view {
  padding: 6px 12px;
  background: #0d6efd;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.btn-view:hover {
  background: #0b5ed7;
}

.btn-edit {
  padding: 6px 12px;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.btn-submit {
  padding: 6px 12px;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.btn-send-eod {
  padding: 6px 12px;
  background: #198754;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.btn-send-eod:hover {
  background: #157347;
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
  min-width: 480px;
  max-width: 90vw;
  max-height: 85vh;
  overflow-y: auto;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.send-eod-modal h3 {
  margin: 0 0 8px 0;
  color: #333;
}

.modal-hint {
  font-size: 0.9rem;
  color: #555;
  margin: 0 0 16px 0;
}

.modal-loading {
  padding: 20px;
  text-align: center;
  color: #666;
}

.eod-task-list {
  margin-bottom: 16px;
}

.eod-task-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid #eee;
}

.eod-task-row input[type="checkbox"] {
  width: auto;
  margin-top: 6px;
}

.eod-task-main {
  flex: 1;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-start;
}

.eod-task-name {
  flex: 0 1 auto;
  font-weight: 500;
  color: #333;
  margin: 0;
}

.eod-task-project {
  font-size: 12px;
  color: #666;
}

.eod-task-note {
  min-width: 200px;
  width: 100%;
  font-size: 13px;
  padding: 6px 8px;
  resize: vertical;
}

.eod-task-hours {
  display: flex;
  align-items: center;
  gap: 8px;
}

.eod-task-hours label { font-size: 12px; color: #555; margin: 0; }
.eod-task-hours input { width: 70px; padding: 6px 8px; }

.eod-extra-section {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid #ddd;
}

.eod-extra-title {
  margin: 0 0 12px 0;
  font-size: 1rem;
  color: #333;
}

.eod-extra-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 12px;
}

.eod-extra-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.eod-extra-row label { min-width: 80px; font-size: 13px; }
.eod-extra-select, .eod-extra-input { padding: 8px 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
.eod-extra-select { min-width: 200px; }
.eod-extra-input { flex: 1; min-width: 180px; }
.eod-extra-hours input { width: 80px; }

.btn-add-extra {
  padding: 8px 16px;
  background: #265b99;
  color: #fff;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 13px;
  align-self: flex-start;
}

.btn-add-extra:hover { background: #1e4a7a; }

.eod-extra-list { margin-top: 12px; }
.eod-extra-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: #f8f9fa;
  border-radius: 6px;
  margin-bottom: 8px;
  font-size: 14px;
}

.eod-extra-item .ex-project { font-weight: 600; color: #333; min-width: 120px; }
.eod-extra-item .ex-summary { flex: 1; color: #555; }
.eod-extra-item .ex-hrs { color: #265b99; font-size: 13px; }
.btn-remove-extra {
  padding: 4px 10px;
  background: #dc3545;
  color: #fff;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.modal-error {
  background: #f8d7da;
  color: #721c24;
  padding: 10px;
  border-radius: 5px;
  margin-bottom: 12px;
  font-size: 14px;
}

.modal-actions {
  display: flex;
  gap: 12px;
}

.modal-actions .btn-primary {
  padding: 10px 20px;
  background: #198754;
  border: none;
  border-radius: 5px;
  color: white;
  font-weight: 500;
  cursor: pointer;
}

.modal-actions .btn-primary:hover:not(:disabled) {
  background: #157347;
}

.modal-actions .btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.modal-actions .btn-cancel {
  padding: 10px 20px;
  background: #6c757d;
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
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

.pagination button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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
