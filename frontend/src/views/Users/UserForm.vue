<template>
  <div class="user-form">
    <div class="header">
      <h1>{{ isEdit ? 'Edit User' : 'Create User' }}</h1>
      <router-link to="/users" class="btn-back">Back to Users</router-link>
    </div>

    <form @submit.prevent="handleSubmit" v-if="!loading" autocomplete="off">
      <div class="form-grid">
        <div class="form-group">
          <label>Name *</label>
          <input type="text" v-model="form.name" required autocomplete="off" />
        </div>

        <div class="form-group">
          <label>Email *</label>
          <input type="email" v-model="form.email" required autocomplete="off" />
        </div>

        <div class="form-group">
          <label>Employee ID *</label>
          <input type="text" v-model="form.employee_id" required autocomplete="off" />
        </div>

        <div class="form-group">
          <label>Phone</label>
          <input type="text" v-model="form.phone" autocomplete="off" />
        </div>

        <div class="form-group">
          <label>Password {{ isEdit ? '(leave blank to keep current)' : '*' }}</label>
          <input type="password" v-model="form.password" :required="!isEdit" autocomplete="new-password" />
        </div>

        <div class="form-group">
          <label>Status</label>
          <select v-model="form.status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="suspended">Suspended</option>
          </select>
        </div>

        <div class="form-group">
          <label>Role *</label>
          <select v-model="form.roleId" required>
            <option value="">Select role</option>
            <option v-for="role in availableRoles" :key="role.id" :value="role.id">{{ role.name }}</option>
          </select>
        </div>

        <!-- Single team (Employee / Team Lead) -->
        <div class="form-group" v-if="!isManager">
          <label>Department / Team</label>
          <select v-model="singleTeamId">
            <option value="">Select department (optional)</option>
            <option v-for="team in departmentsForRoleDropdown" :key="team.id" :value="team.id">{{ team.name }}</option>
          </select>
        </div>
        <!-- Multiple departments (Manager) - multiple select -->
        <div class="form-group full-width" v-if="isManager">
          <label>Department / Team</label>
          <p class="field-hint" style="margin-bottom: 8px;">You can select multiple departments.</p>
          <div class="team-checkbox-list">
            <label v-for="team in availableTeams" :key="team.id" class="team-checkbox-item">
              <input
                type="checkbox"
                :value="team.id"
                :checked="form.team_ids.includes(team.id)"
                @change="toggleTeamId(team.id)"
              />
              <span>{{ team.name }}</span>
            </label>
          </div>
          <span v-if="form.team_ids.length" class="field-hint">Selected: {{ form.team_ids.length }} department(s)</span>
        </div>

        <!-- Team Lead role: us department ke Manager ko select krne ka dropdown -->
        <div class="form-group full-width" v-if="isTeamLead && selectedTeam">
          <label>Manager (of this department)</label>
          <select v-model="form.selected_manager_id" class="team-lead-select">
            <option value="">Select manager</option>
            <option v-for="m in managersForDropdown" :key="m.id" :value="m.id">
              {{ m.name }} ({{ m.email }})
            </option>
            <option v-if="managersForDropdown.length === 0 && !managersLoading" value="" disabled>— No manager available —</option>
          </select>
          <span v-if="managersLoading" class="field-hint">Loading managers...</span>
        </div>

        <!-- 2) Employee role: us department ke saare TL dropdown me -->
        <div class="form-group full-width" v-if="isEmployee && selectedTeam">
          <label>Team Lead</label>
          <select v-model="form.selected_team_lead_id" class="team-lead-select">
            <option value="">Select team lead</option>
            <option v-for="tl in departmentTeamLeads" :key="tl.id" :value="tl.id">
              {{ tl.name }} ({{ tl.email }})
            </option>
            <option v-if="departmentTeamLeads.length === 0 && !teamLeadsLoading" value="" disabled>— No Team Lead in this department —</option>
          </select>
          <span v-if="teamLeadsLoading" class="field-hint">Loading...</span>
          <p v-else-if="departmentTeamLeads.length === 0 && selectedTeam" class="field-hint hint-warning">
            Is department mein TL dikhane ke liye: <strong>Teams</strong> → <strong>{{ selectedTeam.name }}</strong> → Edit → <strong>Team Lead</strong> select karein, aur us user ko <strong>Members</strong> mein add karein.
          </p>
        </div>

        <div class="form-group checkbox-group">
          <label>
            <input type="checkbox" v-model="form.overtime_allowed" />
            Overtime Allowed
          </label>
        </div>
      </div>

      <div v-if="error" class="error-message">{{ error }}</div>

      <div class="form-actions">
        <button type="submit" class="btn-primary" :disabled="submitting">
          {{ submitting ? 'Saving...' : (isEdit ? 'Update User' : 'Create User') }}
        </button>
        <router-link to="/users" class="btn-cancel">Cancel</router-link>
      </div>
    </form>

    <div v-if="loading" class="loading">Loading user data...</div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { userService } from '../../services/userService'
import { roleService } from '../../services/roleService'
import { teamService } from '../../services/teamService'
import { logger } from '../../utils/logger'

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => !!route.params.id)

const form = ref({
  name: '',
  email: '',
  employee_id: '',
  phone: '',
  password: '',
  status: 'active',
  overtime_allowed: false,
  roleId: '',
  team_ids: [],
  selected_team_lead_id: '',
  selected_manager_id: ''
})

const availableRoles = ref([])
const availableTeams = ref([])
const availableManagers = ref([])
const managersLoading = ref(false)
const selectedTeamDetail = ref(null)
const teamLeadsLoading = ref(false)
const loading = ref(false)
const submitting = ref(false)
const error = ref('')

const isManager = computed(() => ['project_manager', 'manager'].includes(selectedRoleSlug.value))
const isTeamLead = computed(() => ['team_lead', 'team_leader'].includes(selectedRoleSlug.value))
const isEmployee = computed(() => selectedRoleSlug.value === 'employee')

/** Employee/Team Lead: department dropdown – all departments */
const departmentsForRoleDropdown = computed(() => {
  return availableTeams.value || []
})

/** Team Lead: Manager dropdown – this dept's manager plus others (no duplicates) */
const managersForDropdown = computed(() => {
  const pm = selectedTeam.value?.project_manager
  const list = availableManagers.value || []
  if (pm) {
    const rest = list.filter(m => m.id !== pm.id)
    return [pm, ...rest]
  }
  return list
})

const singleTeamId = computed({
  get () {
    const ids = form.value.team_ids
    return (ids && ids[0]) ? ids[0] : ''
  },
  set (v) {
    form.value.team_ids = v ? [Number(v)] : []
  }
})

const selectedTeam = computed(() => {
  const id = form.value.team_ids && form.value.team_ids[0]
  if (!id) return null
  return availableTeams.value.find(t => t.id === Number(id)) || null
})

/** All Team Leads of this department (Employee dropdown) – from detail first, else from list team_lead */
const departmentTeamLeads = computed(() => {
  const detail = selectedTeamDetail.value
  const fromDetail = detail && Array.isArray(detail.team_leads) ? detail.team_leads : []
  if (fromDetail.length > 0) return fromDetail
  const team = selectedTeam.value
  if (team && team.team_lead) return [team.team_lead]
  return []
})

const selectedRoleSlug = computed(() => {
  if (!form.value.roleId) return null
  const role = availableRoles.value.find(r => r.id === Number(form.value.roleId))
  return role ? role.slug : null
})

/** One dynamic field: "Team Lead" for Employee, "Manager" for Team Lead */
const reportToLabel = computed(() => {
  if (!selectedTeam.value || !selectedRoleSlug.value) return null
  if (selectedRoleSlug.value === 'employee') return 'Team Lead'
  if (selectedRoleSlug.value === 'team_lead') return 'Manager'
  return null
})

const reportToName = computed(() => {
  if (!selectedTeam.value || !reportToLabel.value) return '—'
  if (reportToLabel.value === 'Team Lead') {
    return selectedTeam.value.team_lead ? selectedTeam.value.team_lead.name : '—'
  }
  if (reportToLabel.value === 'Manager') {
    return selectedTeam.value.project_manager ? selectedTeam.value.project_manager.name : '—'
  }
  return '—'
})

function toggleTeamId (teamId) {
  const id = Number(teamId)
  const idx = form.value.team_ids.indexOf(id)
  if (idx >= 0) {
    form.value.team_ids = form.value.team_ids.filter(t => t !== id)
  } else {
    form.value.team_ids = [...form.value.team_ids, id]
  }
}

const loadRoles = async () => {
  try {
    const response = await roleService.getAll()
    if (response.data.success) {
      availableRoles.value = Array.isArray(response.data.data) ? response.data.data : []
    }
  } catch (err) {
    logger.error('Failed to load roles', err)
  }
}

const loadTeams = async () => {
  try {
    const response = await teamService.getAll({ per_page: 100 })
    if (response.data.success) {
      const list = response.data.data
      availableTeams.value = Array.isArray(list) ? list : []
    }
  } catch (err) {
    logger.error('Failed to load teams', err)
  }
}

const loadManagers = async () => {
  managersLoading.value = true
  try {
    const [r1, r2] = await Promise.all([
      userService.getAll({ per_page: 100, role: 'project_manager' }),
      userService.getAll({ per_page: 100, role: 'manager' })
    ])
    const list1 = r1.data.success && Array.isArray(r1.data.data) ? r1.data.data : []
    const list2 = r2.data.success && Array.isArray(r2.data.data) ? r2.data.data : []
    const ids = new Set()
    availableManagers.value = [...list1, ...list2].filter(u => {
      if (ids.has(u.id)) return false
      ids.add(u.id)
      return true
    })
  } catch (err) {
    logger.error('Failed to load managers', err)
    availableManagers.value = []
  } finally {
    managersLoading.value = false
  }
}

const loadUser = async () => {
  if (!isEdit.value) return

  loading.value = true
  try {
    const response = await userService.getById(route.params.id)
    if (response.data.success) {
      const user = response.data.data
      const firstRole = user.roles && user.roles.length ? user.roles[0] : null
      const firstTeam = user.teams && user.teams.length ? user.teams[0] : null
      form.value = {
        name: user.name,
        email: user.email,
        employee_id: user.employee_id,
        phone: user.phone || '',
        password: '',
        status: user.status,
        overtime_allowed: user.overtime_allowed,
        roleId: firstRole ? firstRole.id : '',
        team_ids: user.teams && user.teams.length ? user.teams.map(t => t.id) : [],
        selected_team_lead_id: '',
        selected_manager_id: ''
      }
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load user'
  } finally {
    loading.value = false
  }
}

const handleSubmit = async () => {
  submitting.value = true
  error.value = ''

  try {
    const userData = {
      name: form.value.name,
      email: form.value.email,
      employee_id: form.value.employee_id,
      phone: form.value.phone,
      status: form.value.status,
      overtime_allowed: form.value.overtime_allowed,
      roles: form.value.roleId ? [form.value.roleId] : [],
      team_ids: form.value.team_ids && form.value.team_ids.length ? form.value.team_ids.map(id => Number(id)) : []
    }
    if (form.value.password) userData.password = form.value.password
    if (isTeamLead.value && form.value.selected_manager_id) {
      userData.project_manager_id = Number(form.value.selected_manager_id)
    }

    if (isEdit.value) {
      await userService.update(route.params.id, userData)
    } else {
      await userService.create(userData)
    }

    router.push('/users')
  } catch (err) {
    if (err.response?.data?.errors) {
      const errors = err.response.data.errors
      const firstError = Object.values(errors)[0]
      error.value = Array.isArray(firstError) ? firstError[0] : firstError
    } else {
      error.value = err.response?.data?.message || 'Failed to save user'
    }
  } finally {
    submitting.value = false
  }
}

watch(selectedTeam, (team) => {
  form.value.selected_manager_id = ''
  if (isTeamLead.value && team && team.project_manager) {
    form.value.selected_manager_id = String(team.project_manager.id)
  }
})

watch([selectedTeam, isEmployee, isTeamLead], async ([team, isEmp, isTL]) => {
  form.value.selected_team_lead_id = ''
  selectedTeamDetail.value = null
  if (!team || (!isEmp && !isTL)) return
  if (isTL) {
    loadManagers()
    if (team.project_manager) {
      form.value.selected_manager_id = String(team.project_manager.id)
    } else {
      form.value.selected_manager_id = ''
    }
  }
  teamLeadsLoading.value = true
  try {
    const res = await teamService.getById(team.id)
    if (res.data.success && res.data.data) {
      selectedTeamDetail.value = res.data.data
      if (isEmp) {
        const leads = res.data.data.team_leads
        if (Array.isArray(leads) && leads.length === 1) {
          form.value.selected_team_lead_id = String(leads[0].id)
        }
      }
    }
  } catch (e) {
    selectedTeamDetail.value = null
  } finally {
    teamLeadsLoading.value = false
  }
}, { immediate: true })

onMounted(() => {
  loadRoles()
  loadTeams()
  loadUser()
})
</script>

<style scoped>
.user-form {
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
  color: #404040;
  font-size: 2rem;
}

.btn-back {
  padding: 10px 20px;
  background: #404040;
  color: white;
  text-decoration: none;
  border-radius: 8px;
  font-weight: 500;
  transition: background 0.2s;
}

.btn-back:hover {
  background: #265b99;
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
  color: #404040;
}

.form-group input,
.form-group select {
  padding: 10px 12px;
  border: 1px solid var(--gt-border, #d8dce0);
  border-radius: 8px;
  font-size: 14px;
  color: #404040;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #265b99;
  box-shadow: 0 0 0 2px rgba(38, 91, 153, 0.2);
}

.checkbox-group label {
  flex-direction: row;
  align-items: center;
  gap: 8px;
}

.team-checkbox-list {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 20px;
  padding: 14px 16px;
  border: 1px solid var(--gt-border, #d8dce0);
  border-radius: 8px;
  background: #fff;
  max-height: 200px;
  overflow-y: auto;
}

.team-checkbox-item {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-weight: 500;
  color: #404040;
}

.team-checkbox-item input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: #265b99;
}

.info-box {
  padding: 12px 16px;
  background: rgba(38, 91, 153, 0.08);
  border: 1px solid rgba(38, 91, 153, 0.3);
  border-radius: 8px;
  color: #404040;
  margin-bottom: 0;
}
.info-box .info-email {
  font-size: 0.9em;
  color: #5a5a5a;
}
.field-hint {
  display: block;
  font-size: 0.85rem;
  color: #5a5a5a;
  margin-top: 4px;
}
.hint-warning {
  margin-top: 8px;
  padding: 10px 12px;
  background: rgba(38, 91, 153, 0.1);
  border-radius: 6px;
  color: #404040;
  line-height: 1.4;
}

.error-message {
  background: #f5f5f5;
  color: #404040;
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 20px;
  border-left: 4px solid #265b99;
}

.form-actions {
  display: flex;
  gap: 15px;
  margin-top: 30px;
}

.btn-primary {
  padding: 12px 24px;
  background: #404040;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-primary:hover:not(:disabled) {
  background: #265b99;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-cancel {
  padding: 12px 24px;
  background: #5a5a5a;
  color: white;
  text-decoration: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 500;
  display: inline-block;
  transition: background 0.2s;
}

.btn-cancel:hover {
  background: #265b99;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #404040;
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
