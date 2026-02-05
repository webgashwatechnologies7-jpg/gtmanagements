<template>
  <div class="project-list">
    <div class="header">
      <h1>Project Management</h1>
      <router-link v-if="canAddProject" to="/projects/create" class="btn-primary">Add New Project</router-link>
    </div>

    <div v-if="successMessage" class="success">{{ successMessage }}</div>

    <!-- Filters -->
    <div class="filters">
      <input
        type="text"
        v-model="filters.search"
        placeholder="Search projects..."
        @input="debouncedSearch"
        class="search-input"
      />
      <select v-model="filters.status" @change="loadProjects" class="filter-select">
        <option value="">All Status</option>
        <option value="planning">Planning</option>
        <option value="active">Active</option>
        <option value="on_hold">On Hold</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <select v-model="filters.priority" @change="loadProjects" class="filter-select">
        <option value="">All Priority</option>
        <option value="high">High</option>
        <option value="medium">Medium</option>
        <option value="low">Low</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading projects...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Projects Table -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Type</th>
            <th>Priority</th>
            <th>Status</th>
            <th>PM</th>
            <th>Team</th>
            <th>Deadline</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="project in (projects || [])" :key="project.id">
            <td>{{ project.id }}</td>
            <td>
              <router-link :to="`/projects/${project.id}`" class="project-link">
                {{ project.name }}
              </router-link>
            </td>
            <td>{{ project.project_type?.name || '-' }}</td>
            <td>
              <span :class="['priority-badge', project.priority]">
                {{ project.priority }}
              </span>
            </td>
            <td>
              <template v-if="canChangeStatus">
                <select
                  class="status-select"
                  :value="project.status"
                  @change="(e) => updateProjectStatus(project.id, e.target.value)"
                  :disabled="assigningProjectId === project.id"
                >
                  <option value="planning">Planning</option>
                  <option value="active">Active</option>
                  <option value="on_hold">On Hold</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </template>
              <template v-else>
                <span :class="['status-badge', project.status]">
                  {{ project.status }}
                </span>
              </template>
            </td>
            <td>{{ project.project_manager?.name || '-' }}</td>
            <td>{{ project.team?.name || '-' }}</td>
            <td>{{ project.deadline || '-' }}</td>
            <td class="actions">
              <router-link :to="`/projects/${project.id}`" class="btn-view">View</router-link>
              <button v-if="canEditProject" @click="editProject(project.id)" class="btn-edit">Edit</button>

              <!-- Admin/PM: assign TL -->
              <div v-if="canAssignTL" class="assign-wrap">
                <select
                  class="assign-select"
                  :value="project.team_lead?.id || ''"
                  @change="(e) => assignToTeamLead(project.id, e.target.value)"
                  :disabled="assigningProjectId === project.id"
                >
                  <option v-if="!project.team_lead" value="">Assign TL</option>
                  <option v-else :value="project.team_lead.id">
                    {{ project.team_lead.name }}
                  </option>
                  <optgroup
                    v-for="team in teamLeaderGroups"
                    :key="team.id"
                    :label="team.name"
                  >
                    <option
                      v-for="tl in team.team_leads"
                      :key="tl.id"
                      :value="tl.id"
                    >
                      {{ tl.name }}
                    </option>
                  </optgroup>
                </select>
              </div>

              <!-- TL: assign to my team members -->
              <div v-else-if="isTL" class="assign-wrap">
                <select
                  class="assign-select"
                  :value="''"
                  @change="(e) => assignToMember(project.id, e.target.value)"
                  :disabled="assigningProjectId === project.id"
                >
                  <option value="">Assign Member</option>
                  <option v-for="m in teamMembers" :key="m.id" :value="m.id">
                    {{ m.name }}
                  </option>
                </select>
              </div>

              <!-- Employee / assigned user: Create Task for this project -->
              <router-link
                v-if="canCreateTaskForProject"
                :to="`/tasks/create?project_id=${project.id}`"
                class="btn-create-task"
              >
                Create Task
              </router-link>

              <button v-if="canDeleteProject" @click="deleteProject(project.id)" class="btn-delete">Delete</button>
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
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { projectService } from '../../services/projectService'
import { teamService } from '../../services/teamService'
import { teamMemberService } from '../../services/teamMemberService'
import { useAuthStore } from '../../stores/auth'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'
import { logger } from '../../utils/logger'

const router = useRouter()
const toast = useToast()
const confirmStore = useConfirm()
const authStore = useAuthStore()
const isTL = computed(() => authStore.hasAnyRole(['team_lead', 'team_leader']))
const isEmployee = computed(() => authStore.hasRole('employee'))
const isAdminOrPM = computed(() => authStore.hasAnyRole(['admin', 'project_manager']))
const isSales = computed(() => authStore.hasRole('sales'))

// Sales: can create projects, edit/delete their own, assign TL to their own projects
// Employee: can only view projects, change status and create tasks (on their assigned projects)
const canAddProject = computed(() => isAdminOrPM.value || isSales.value)
const canEditProject = computed(() => isAdminOrPM.value || isTL.value || isSales.value)
const canDeleteProject = computed(() => isAdminOrPM.value || isSales.value)
const canAssignTL = computed(() => isAdminOrPM.value || isSales.value)
const canChangeStatus = computed(() => isTL.value || isEmployee.value || isSales.value)
// Employee / TL: projects in list are assigned to them, so they can create tasks
const canCreateTaskForProject = computed(() => isEmployee.value || isTL.value)

const projects = ref([])
const loading = ref(false)
const error = ref('')
const filters = ref({
  search: '',
  status: '',
  priority: ''
})
const meta = ref(null)
const teams = ref([])
const assigningProjectId = ref(null)
const successMessage = ref('')
const teamMembers = ref([])

let searchTimeout = null

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadProjects()
  }, 500)
}

const loadProjects = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      per_page: 15,
      ...filters.value
    }

    // Remove empty filters
    Object.keys(params).forEach(key => {
      if (params[key] === '') delete params[key]
    })

    const response = await projectService.getAll(params)
    if (response.data.success) {
      const data = response.data.data
      projects.value = Array.isArray(data) ? data : (data?.data ?? [])
      meta.value = response.data.meta ?? null
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load projects'
  } finally {
    loading.value = false
  }
}

const loadTeamsForTLDropdown = async () => {
  try {
    const response = await teamService.getAll({ per_page: 200, status: 'active' })
    if (response.data.success) {
      const data = response.data.data
      teams.value = Array.isArray(data) ? data : (data?.data ?? [])
    }
  } catch (err) {
    logger.error('Failed to load teams', err)
  }
}

const loadMyTeamMembers = async () => {
  if (!isTL.value) return
  try {
    const res = await teamMemberService.getMyMembers()
    if (res.data.success) {
      const members = res.data.data?.members || []
      const myId = authStore.user?.id
      teamMembers.value = Array.isArray(members)
        ? members.filter(m => String(m.id) !== String(myId))
        : []
    }
  } catch (err) {
    logger.error('Failed to load team members', err)
  }
}

const assignToMember = async (projectId, employeeId) => {
  if (!employeeId) return
  assigningProjectId.value = projectId
  try {
    await projectService.assignToEmployee(projectId, employeeId)
    successMessage.value = 'Project assigned successfully.'
    setTimeout(() => {
      successMessage.value = ''
    }, 2500)
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to assign member'))
  } finally {
    assigningProjectId.value = null
  }
}

const teamLeaderGroups = computed(() => {
  const list = Array.isArray(teams.value) ? teams.value : []
  return list
    .map((t) => ({
      id: t.id,
      name: t.name,
      team_leads: Array.isArray(t.team_leads) && t.team_leads.length
        ? t.team_leads
        : (t.team_lead ? [t.team_lead] : []),
    }))
    .filter(t => t.team_leads.length > 0)
})

const assignToTeamLead = async (projectId, teamLeadId) => {
  if (!teamLeadId) return
  assigningProjectId.value = projectId
  try {
    await projectService.assignToTL(projectId, teamLeadId)
    await loadProjects()
    successMessage.value = 'Project assigned successfully.'
    setTimeout(() => {
      successMessage.value = ''
    }, 2500)
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to assign Team Lead'))
  } finally {
    assigningProjectId.value = null
  }
}

const changePage = (page) => {
  filters.value.page = page
  loadProjects()
}

const editProject = (id) => {
  router.push(`/projects/${id}/edit`)
}

const deleteProject = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to delete this project?')
  if (!ok) return
  try {
    await projectService.delete(id)
    loadProjects()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to delete project'))
  }
}

const updateProjectStatus = async (projectId, status) => {
  if (!status) return
  assigningProjectId.value = projectId
  try {
    await projectService.update(projectId, { status })
    await loadProjects()
    successMessage.value = 'Status updated successfully.'
    setTimeout(() => {
      successMessage.value = ''
    }, 2500)
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to update status'))
  } finally {
    assigningProjectId.value = null
  }
}

onMounted(() => {
  loadProjects()
  loadTeamsForTLDropdown()
  loadMyTeamMembers()
})
</script>

<style scoped>
.project-list {
  padding: 20px;
  max-width: 1600px;
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
  transition: background 0.3s;
}

.btn-primary:hover {
  background: #333;
}

.filters {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.search-input {
  flex: 1;
  min-width: 250px;
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
  min-width: 150px;
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

.data-table tbody tr:hover {
  background: #f9f9f9;
}

.project-link {
  color: #1a1a1a;
  text-decoration: none;
  font-weight: 500;
}

.project-link:hover {
  text-decoration: underline;
}

.priority-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.priority-badge.high {
  background: #ddd;
  color: #333;
}

.priority-badge.medium {
  background: #e8e8e8;
  color: #333;
}

.priority-badge.low {
  background: #e0e0e0;
  color: #333;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
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
  background: #e8e8e8;
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

.actions {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.assign-wrap {
  display: flex;
}

.assign-select {
  padding: 6px 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 12px;
  background: white;
}

.status-select {
  padding: 6px 10px;
  border: 1px solid #ddd;
  border-radius: 999px;
  font-size: 12px;
  background: white;
}

.btn-view {
  padding: 6px 12px;
  background: #1a1a1a;
  color: white;
  text-decoration: none;
  border-radius: 4px;
  font-size: 12px;
}

.btn-view:hover {
  background: #138496;
}

.btn-create-task {
  padding: 6px 12px;
  background: #0d6efd;
  color: white;
  text-decoration: none;
  border-radius: 4px;
  font-size: 12px;
  white-space: nowrap;
}

.btn-create-task:hover {
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

.btn-edit:hover {
  background: #218838;
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

.btn-delete:hover {
  background: #c82333;
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

.pagination button:not(:disabled):hover {
  background: #f5f5f5;
}

.loading, .error {
  text-align: center;
  padding: 40px;
  color: #666;
}

.error {
  color: #333;
}

.success {
  background: #eef6ee;
  border: 1px solid #cfe6cf;
  color: #1e4620;
  padding: 10px 12px;
  border-radius: 6px;
  margin-bottom: 15px;
}
</style>
