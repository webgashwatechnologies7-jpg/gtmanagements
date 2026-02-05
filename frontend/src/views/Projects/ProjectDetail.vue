<template>
  <div class="project-detail">
    <div class="header">
      <h1>Project Details</h1>
      <div class="header-actions">
        <router-link v-if="canCreateTask" :to="`/tasks/create?project_id=${projectId}`" class="btn-create-task">Create Task</router-link>
        <router-link v-if="canEditProject" :to="`/projects/${projectId}/edit`" class="btn-edit">Edit Project</router-link>
        <router-link to="/projects" class="btn-back">Back to Projects</router-link>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading project details...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Project Info -->
    <div v-if="!loading && !error && project" class="project-info">
      <div class="info-card">
        <h2>{{ project.name }}</h2>
        <p v-if="project.description" class="description">{{ project.description }}</p>
        
        <div class="info-grid">
          <div class="info-item">
            <label>Status:</label>
            <select
              v-if="canChangeStatus"
              class="status-select"
              :value="project.status"
              @change="(e) => updateProjectStatus(e.target.value)"
              :disabled="updatingStatus"
            >
              <option value="planning">Planning</option>
              <option value="active">Active</option>
              <option value="on_hold">On Hold</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
            <span v-else :class="['status-badge', project.status]">{{ project.status }}</span>
          </div>
          <div class="info-item">
            <label>Priority:</label>
            <span :class="['priority-badge', project.priority]">{{ project.priority }}</span>
          </div>
          <div class="info-item" v-if="project.project_type">
            <label>Project Type:</label>
            <span>{{ project.project_type.name }}</span>
          </div>
          <div class="info-item" v-if="project.project_manager">
            <label>Project Manager:</label>
            <span>{{ project.project_manager.name }} ({{ project.project_manager.email }})</span>
          </div>
          <div class="info-item" v-if="project.team">
            <label>Team:</label>
            <span>{{ project.team.name }}</span>
          </div>
          <div class="info-item" v-if="project.deadline">
            <label>Deadline:</label>
            <span>{{ project.deadline }}</span>
          </div>
          <div class="info-item" v-if="project.estimated_hours">
            <label>Estimated Hours:</label>
            <span>{{ project.estimated_hours }}h</span>
          </div>
          <div class="info-item" v-if="project.actual_hours">
            <label>Actual Hours:</label>
            <span>{{ project.actual_hours }}h</span>
          </div>
          <div class="info-item" v-if="project.start_date">
            <label>Start Date:</label>
            <span>{{ project.start_date }}</span>
          </div>
          <div class="info-item" v-if="project.completion_date">
            <label>Completion Date:</label>
            <span>{{ project.completion_date }}</span>
          </div>
        </div>
      </div>

      <!-- Custom / Dynamic Fields -->
      <div v-if="customFieldEntries.length" class="details-card">
        <h3>Project Details</h3>
        <div class="details-grid">
          <div v-for="item in customFieldEntries" :key="item.key" class="details-item">
            <label>{{ item.label }}</label>
            <div class="value">{{ item.value }}</div>
          </div>
        </div>
      </div>

      <!-- Assigned Users -->
      <div class="assigned-section">
        <div class="section-header">
          <h3>Assigned Users ({{ project.assigned_users?.length || 0 }})</h3>
        </div>

        <div v-if="project.assigned_users && project.assigned_users.length > 0" class="assigned-list">
          <div v-for="user in project.assigned_users" :key="user.id" class="assigned-card">
            <div class="assigned-info">
              <h4>{{ user.name }}</h4>
              <p>{{ user.email }}</p>
              <span class="level-badge">{{ user.assignment_level.toUpperCase() }}</span>
              <small>Assigned: {{ user.assigned_at }}</small>
            </div>
          </div>
        </div>
        <div v-else class="empty-state">
          <p>No users assigned to this project yet.</p>
        </div>
      </div>

      <!-- Project History (who created, assigned, changed status, deleted – this history is not deleted) -->
      <div class="history-section">
        <div class="section-header">
          <h3>Project History</h3>
        </div>
        <div v-if="historyLoading" class="history-loading">Loading history...</div>
        <div v-else-if="historyError" class="history-error">{{ historyError }}</div>
        <div v-else-if="historyList.length === 0" class="empty-state">
          <p>No history recorded yet.</p>
        </div>
        <div v-else class="history-timeline">
          <div
            v-for="(entry, index) in historyList"
            :key="entry.id"
            class="history-item"
            :class="entry.action"
          >
            <div class="history-dot"></div>
            <div class="history-content">
              <span class="history-label">{{ historyLabel(entry) }}</span>
              <span class="history-meta">
                {{ entry.user?.name || 'System' }}
                <template v-if="entry.created_at"> · {{ formatDate(entry.created_at) }}</template>
              </span>
              <div v-if="entry.new_values && hasDetails(entry)" class="history-details">
                {{ formatDetails(entry) }}
              </div>
            </div>
          </div>
        </div>
        <div v-if="historyMeta && historyMeta.last_page > 1" class="history-pagination">
          <button
            :disabled="historyPage <= 1"
            @click="historyPage--; loadHistory()"
          >
            Previous
          </button>
          <span>Page {{ historyPage }} of {{ historyMeta.last_page }}</span>
          <button
            :disabled="historyPage >= historyMeta.last_page"
            @click="historyPage++; loadHistory()"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { projectService } from '../../services/projectService'
import { useAuthStore } from '../../stores/auth'
import { useToast } from '../../stores/toast'
import { getErrorMessage } from '../../utils/errorMessage'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const projectId = computed(() => route.params.id)

const isTL = computed(() => authStore.hasAnyRole(['team_lead', 'team_leader']))
const isEmployee = computed(() => authStore.hasRole('employee'))
const isAdminOrPM = computed(() => authStore.hasAnyRole(['admin', 'project_manager']))
const canEditProject = computed(() => isAdminOrPM.value || isTL.value)
const canChangeStatus = computed(() => isTL.value || isEmployee.value)
// Employee / TL: can create tasks on assigned project (access to detail page means they have access)
const canCreateTask = computed(() => isEmployee.value || isTL.value || isAdminOrPM.value)

const updatingStatus = ref(false)

const updateProjectStatus = async (status) => {
  if (!status) return
  updatingStatus.value = true
  try {
    await projectService.update(projectId.value, { status })
    project.value = { ...project.value, status }
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to update status'))
  } finally {
    updatingStatus.value = false
  }
}

const project = ref(null)
const loading = ref(false)
const error = ref('')

const historyList = ref([])
const historyLoading = ref(false)
const historyError = ref('')
const historyMeta = ref(null)
const historyPage = ref(1)

const getWebDevelopmentFallbackSchema = () => ([
  { key: 'date', label: 'Date', type: 'date' },
  { key: 'assigned_to', label: 'Assigned To', type: 'text' },
  { key: 'due_date', label: 'Due Date', type: 'date' },
  { key: 'company_name', label: 'Company Name', type: 'text' },
  { key: 'domain_name', label: 'Domain Name', type: 'text' },
  { key: 'hosting_needed', label: 'Hosting Needed', type: 'boolean' },
  { key: 'ssl', label: 'SSL', type: 'boolean' },
  { key: 'business_mail', label: 'Business Mail', type: 'text' },
  { key: 'predefined_pages', label: 'Pre-defined Pages', type: 'checkbox-group' },
  { key: 'website_structure', label: 'Website Structure', type: 'select' },
  { key: 'popup_form', label: 'Popup Form', type: 'boolean' },
  { key: 'project_details', label: 'Project Details', type: 'textarea' },
  { key: 'whatsapp', label: 'WhatsApp', type: 'text' },
  { key: 'additional', label: 'Additional', type: 'text' },
  { key: 'mobile_no', label: 'Mobile No.', type: 'text' },
  { key: 'gmb_needed', label: 'GMB Needed', type: 'boolean' },
  { key: 'live_chat', label: 'Live Chat', type: 'boolean' },
  { key: 'payment_gateway', label: 'Payment Gateway', type: 'boolean' },
  { key: 'pay_now', label: 'Pay Now', type: 'boolean' },
  { key: 'address_bank_details', label: 'Address / Bank Details', type: 'textarea' },
  { key: 'logo', label: 'Logo', type: 'boolean' },
  { key: 'social_media', label: 'Social Media', type: 'checkbox-group' },
  { key: 'form_entries', label: 'Form Entries', type: 'checkbox-group' }
])

const getSeoFallbackSchema = () => ([
  { key: 'mail_logins', label: 'Mail Logins', type: 'textarea' },
  { key: 'hosting_logins', label: 'Hosting Logins', type: 'textarea' },
  { key: 'website_link', label: 'Website Link', type: 'text' },
  { key: 'descriptions', label: 'Descriptions', type: 'textarea' }
])

const getGoogleAdsFallbackSchema = () => ([
  { key: 'landing_page', label: 'Landing Page', type: 'text' },
  { key: 'gmail_logins', label: 'Gmail Logins', type: 'textarea' },
  { key: 'descriptions', label: 'Descriptions', type: 'textarea' }
])

const customFieldSchema = computed(() => {
  const p = project.value
  if (!p) return []

  const type = p.project_type || null
  const typeFields = type && Array.isArray(type.fields) ? type.fields : []
  if (typeFields.length) return typeFields

  const slug = String(type?.slug || '').toLowerCase()
  const name = String(type?.name || '').toLowerCase()

  if (slug === 'web-development-project' || name === 'web developement project' || name === 'web development project') {
    return getWebDevelopmentFallbackSchema()
  }
  if (slug === 'seo-project' || name === 'seo project') return getSeoFallbackSchema()
  if (slug === 'google-ads-project' || name === 'google ads project') return getGoogleAdsFallbackSchema()

  return []
})

const formatCustomValue = (val) => {
  if (val === null || val === undefined) return ''
  if (typeof val === 'boolean') return val ? 'Yes' : 'No'
  if (Array.isArray(val)) return val.length ? val.join(', ') : ''
  if (typeof val === 'object') return JSON.stringify(val)
  return String(val)
}

const customFieldEntries = computed(() => {
  const p = project.value
  if (!p) return []
  const data = p.custom_fields && typeof p.custom_fields === 'object' ? p.custom_fields : {}

  const schema = customFieldSchema.value
  const schemaKeys = new Set(schema.map(f => f.key))

  const fromSchema = schema
    .map((f) => ({
      key: f.key,
      label: f.label || f.key,
      value: formatCustomValue(data[f.key]),
    }))
    .filter(i => i.value !== '')

  // If some keys exist but not in schema, show them too
  const extras = Object.keys(data)
    .filter(k => !schemaKeys.has(k))
    .map(k => ({
      key: k,
      label: k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
      value: formatCustomValue(data[k]),
    }))
    .filter(i => i.value !== '')

  return [...fromSchema, ...extras]
})

const loadProject = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await projectService.getById(projectId.value)
    if (response.data.success) {
      project.value = response.data.data
      loadHistory()
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load project'
  } finally {
    loading.value = false
  }
}

const loadHistory = async () => {
  if (!projectId.value) return
  historyLoading.value = true
  historyError.value = ''
  try {
    const res = await projectService.getHistory(projectId.value, { page: historyPage.value, per_page: 30 })
    if (res.data.success) {
      historyList.value = res.data.data || []
      historyMeta.value = res.data.meta || null
    }
  } catch (err) {
    historyError.value = err.response?.data?.message || 'Failed to load history'
  } finally {
    historyLoading.value = false
  }
}

const actionLabels = {
  created: 'Project created',
  updated: 'Project updated',
  assigned_pm: 'Assigned to Project Manager',
  assigned_tl: 'Assigned to Team Lead',
  assigned_employee: 'Assigned to Employee',
  deleted: 'Project deleted',
  task_created: 'Task created',
  task_updated: 'Task edited',
  task_deleted: 'Task deleted',
  task_status_changed: 'Task status changed'
}

const historyLabel = (entry) => {
  const taskName = entry.new_values?.task_name || entry.old_values?.task_name || 'Task'
  if (entry.action === 'task_created') return `Task "${taskName}" created`
  if (entry.action === 'task_updated') return `Task "${taskName}" edited`
  if (entry.action === 'task_deleted') return `Task "${taskName}" deleted`
  if (entry.action === 'task_status_changed') {
    const oldS = entry.new_values?.old_status || '-'
    const newS = entry.new_values?.new_status || '-'
    return `Task "${taskName}" status: ${oldS} → ${newS}`
  }
  const label = actionLabels[entry.action] || entry.action
  if (entry.action === 'assigned_employee' && entry.new_values?.assigned_to_name) {
    return `Assigned to Employee: ${entry.new_values.assigned_to_name}`
  }
  if (entry.action === 'assigned_tl' && entry.new_values?.assigned_to_name) {
    return `Assigned to Team Lead: ${entry.new_values.assigned_to_name}`
  }
  if (entry.action === 'assigned_pm' && entry.new_values?.assigned_to_name) {
    return `Assigned to PM: ${entry.new_values.assigned_to_name}`
  }
  if (entry.action === 'updated' && entry.new_values?.status !== undefined) {
    const oldS = entry.old_values?.status
    const newS = entry.new_values?.status
    if (oldS !== newS) return `Status changed: ${oldS || '-'} → ${newS}`
  }
  return label
}

const formatDate = (d) => {
  if (!d) return ''
  const dt = new Date(d)
  return dt.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' })
}

const hasDetails = (entry) => {
  if (entry.action === 'updated') {
    const o = entry.old_values || {}
    const n = entry.new_values || {}
    const keys = [...new Set([...Object.keys(o), ...Object.keys(n)])].filter(
      k => !['updated_at', 'created_at'].includes(k) && o[k] !== n[k]
    )
    return keys.length > 0
  }
  if (entry.action === 'task_updated' && entry.new_values) {
    return !!(entry.new_values.status != null || entry.new_values.priority != null || entry.new_values.name != null)
  }
  return false
}

const formatDetails = (entry) => {
  if (entry.action === 'updated') {
    const o = entry.old_values || {}
    const n = entry.new_values || {}
    const parts = []
    if (o.status !== n.status) parts.push(`Status: ${o.status} → ${n.status}`)
    if (o.priority !== n.priority) parts.push(`Priority: ${o.priority} → ${n.priority}`)
    if (o.name !== n.name) parts.push(`Name: ${o.name} → ${n.name}`)
    return parts.length ? parts.join(' · ') : 'Details updated'
  }
  if (entry.action === 'task_updated' && entry.new_values) {
    const n = entry.new_values
    const parts = []
    if (n.status != null) parts.push(`Status: ${n.status}`)
    if (n.priority != null) parts.push(`Priority: ${n.priority}`)
    if (n.name != null) parts.push(`Name: ${n.name}`)
    return parts.length ? parts.join(' · ') : 'Task updated'
  }
  if (entry.action === 'task_status_changed' && entry.new_values?.new_status) {
    return `New status: ${entry.new_values.new_status}`
  }
  return ''
}

onMounted(() => {
  loadProject()
})
</script>

<style scoped>
.project-detail {
  padding: 20px;
  max-width: 1200px;
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

.header-actions {
  display: flex;
  gap: 10px;
}

.btn-edit {
  padding: 10px 20px;
  background: #1a1a1a;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 500;
}

.btn-edit:hover {
  background: #333;
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

.btn-create-task {
  padding: 10px 20px;
  background: #0d6efd;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 500;
}

.btn-create-task:hover {
  background: #0b5ed7;
}

.info-card {
  background: white;
  padding: 30px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 30px;
}

.details-card {
  background: white;
  padding: 30px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 30px;
}

.details-card h3 {
  color: #333;
  font-size: 1.5rem;
  margin: 0 0 18px 0;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 18px;
}

.details-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.details-item label {
  font-weight: 600;
  color: #555;
  font-size: 0.9rem;
}

.details-item .value {
  color: #333;
  background: #f9f9f9;
  border: 1px solid #eee;
  padding: 10px 12px;
  border-radius: 6px;
  white-space: pre-wrap;
  word-break: break-word;
}

.info-card h2 {
  color: #333;
  margin-bottom: 10px;
  font-size: 1.8rem;
}

.description {
  color: #666;
  margin-bottom: 20px;
  font-size: 1rem;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-top: 20px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.info-item label {
  font-weight: 600;
  color: #555;
  font-size: 0.9rem;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
  display: inline-block;
  width: fit-content;
}

.status-select {
  padding: 6px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  background: white;
}

.status-badge.planning {
  background: #e2e3e5;
  color: #383d41;
}

.status-badge.active {
  background: #e0e0e0;
  color: #333;
}

.status-badge.on_hold {
  background: #fff3cd;
  color: #333;
}

.status-badge.completed {
  background: #e0e0e0;
  color: #333;
}

.status-badge.cancelled {
  background: #ddd;
  color: #333;
}

.priority-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
  display: inline-block;
  width: fit-content;
}

.priority-badge.high {
  background: #ddd;
  color: #333;
}

.priority-badge.medium {
  background: #fff3cd;
  color: #333;
}

.priority-badge.low {
  background: #e0e0e0;
  color: #333;
}

.assigned-section {
  background: white;
  padding: 30px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.section-header {
  margin-bottom: 20px;
}

.section-header h3 {
  color: #333;
  font-size: 1.5rem;
}

.assigned-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.assigned-card {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 20px;
  background: #f9f9f9;
}

.assigned-info h4 {
  margin: 0 0 5px 0;
  color: #333;
}

.assigned-info p {
  margin: 5px 0;
  color: #666;
  font-size: 0.9rem;
}

.level-badge {
  display: inline-block;
  padding: 2px 8px;
  background: #1a1a1a;
  color: white;
  border-radius: 10px;
  font-size: 11px;
  margin-top: 5px;
}

.assigned-info small {
  display: block;
  margin-top: 8px;
  color: #999;
  font-size: 0.85rem;
}

.empty-state {
  text-align: center;
  padding: 40px;
  color: #999;
}

.loading, .error {
  text-align: center;
  padding: 40px;
  color: #666;
}

.error {
  color: #333;
}

/* Project History */
.history-section {
  background: white;
  padding: 30px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-top: 30px;
}

.history-section .section-header {
  margin-bottom: 20px;
}

.history-section .section-header h3 {
  color: #333;
  font-size: 1.5rem;
}

.history-loading, .history-error {
  padding: 20px;
  color: #666;
}

.history-error {
  color: #c82333;
}

.history-timeline {
  position: relative;
  padding-left: 24px;
  border-left: 2px solid #e0e0e0;
  margin-left: 8px;
}

.history-item {
  position: relative;
  padding-bottom: 20px;
}

.history-item:last-child {
  padding-bottom: 0;
}

.history-dot {
  position: absolute;
  left: -30px;
  top: 4px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #1a1a1a;
}

.history-item.created .history-dot { background: #28a745; }
.history-item.updated .history-dot { background: #0d6efd; }
.history-item.assigned_pm .history-dot,
.history-item.assigned_tl .history-dot,
.history-item.assigned_employee .history-dot { background: #6f42c1; }
.history-item.deleted .history-dot { background: #dc3545; }
.history-item.task_created .history-dot { background: #20c997; }
.history-item.task_updated .history-dot { background: #0d6efd; }
.history-item.task_status_changed .history-dot { background: #fd7e14; }
.history-item.task_deleted .history-dot { background: #dc3545; }

.history-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.history-label {
  font-weight: 600;
  color: #333;
}

.history-meta {
  font-size: 0.9rem;
  color: #666;
}

.history-details {
  font-size: 0.85rem;
  color: #555;
  margin-top: 4px;
  padding: 8px;
  background: #f9f9f9;
  border-radius: 4px;
}

.history-pagination {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-top: 20px;
  padding-top: 15px;
  border-top: 1px solid #eee;
}

.history-pagination button {
  padding: 8px 16px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 4px;
  cursor: pointer;
}

.history-pagination button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
