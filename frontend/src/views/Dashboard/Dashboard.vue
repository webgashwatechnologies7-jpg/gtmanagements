<template>
  <div class="dashboard">
    <header class="dashboard-header">
      <h1>Dashboard</h1>
      <p class="dashboard-subtitle">Project & team reports at a glance</p>
    </header>

    <!-- Attendance: Check-in / Check-out (admin ke liye hide) -->
    <section v-if="!isAdmin" class="section attendance-section">
      <h2 class="section-title">Today's Attendance</h2>
      <div class="attendance-card">
        <div v-if="attendanceLoading" class="attendance-loading">Loading...</div>
        <template v-else>
          <div class="attendance-status">
            <span class="attendance-date">{{ todayLabel }}</span>
            <template v-if="todayAttendance">
              <p v-if="todayAttendance.check_in_time" class="attendance-time">
                Check-in: <strong>{{ formatTime(todayAttendance.check_in_time) }}</strong>
              </p>
              <p v-if="todayAttendance.check_out_time" class="attendance-time">
                Check-out: <strong>{{ formatTime(todayAttendance.check_out_time) }}</strong>
                <span v-if="todayAttendance.regular_minutes != null || todayAttendance.total_work_minutes != null" class="shift-hrs">
                  · <strong>Today's shift:</strong> {{ formatShiftHours(todayAttendance) }}
                </span>
              </p>
            </template>
          </div>
          <div class="attendance-actions">
            <button
              v-if="!todayAttendance || !todayAttendance.check_in_time"
              @click="doCheckIn"
              :disabled="attendanceActionLoading"
              class="btn-check-in"
            >
              {{ attendanceActionLoading ? '...' : 'Check In' }}
            </button>
            <button
              v-else-if="!todayAttendance.check_out_time"
              @click="doCheckOut"
              :disabled="attendanceActionLoading"
              class="btn-check-out"
            >
              {{ attendanceActionLoading ? '...' : 'Check Out' }}
            </button>
            <span v-else class="attendance-done">Done for today</span>
            <router-link to="/attendance" class="btn-my-attendance">My Attendance</router-link>
          </div>
        </template>
      </div>
    </section>

    <!-- Leave Requests summary (Admin/HR only) -->
    <section v-if="isAdminOrHR" class="section leave-section">
      <h2 class="section-title">Leave Requests</h2>
      <div v-if="leaveDashboardLoading" class="leave-loading">Loading...</div>
      <div v-else-if="leaveDashboard" class="leave-dashboard-card">
        <p class="leave-hint">Who has applied for leave, which are approved/rejected – approve from here.</p>
        <div class="leave-stats">
          <div class="leave-stat pending">
            <span class="leave-stat-label">Pending</span>
            <span class="leave-stat-value">{{ leaveDashboard.pending_count ?? 0 }}</span>
          </div>
          <div class="leave-stat approved">
            <span class="leave-stat-label">Approved</span>
            <span class="leave-stat-value">{{ leaveDashboard.approved_count ?? 0 }}</span>
          </div>
          <div class="leave-stat rejected">
            <span class="leave-stat-label">Rejected</span>
            <span class="leave-stat-value">{{ leaveDashboard.rejected_count ?? 0 }}</span>
          </div>
        </div>
        <router-link to="/leaves/requests" class="btn-leave-requests">Open Leave Requests</router-link>
      </div>
    </section>

    <div v-if="loading" class="loading">Loading dashboard...</div>
    <div v-else-if="error" class="error">{{ error }}</div>

    <template v-else-if="data">
      <!-- Project stats (admin) -->
      <section v-if="isAdminDashboard" class="section">
        <h2 class="section-title">Projects</h2>
        <div class="stats-row">
          <div class="stat-card total">
            <span class="stat-label">Total Projects</span>
            <span class="stat-value">{{ data.total_projects ?? 0 }}</span>
          </div>
          <div class="stat-card working">
            <span class="stat-label">Working</span>
            <span class="stat-value">{{ data.projects_working ?? 0 }}</span>
          </div>
          <div class="stat-card not-started">
            <span class="stat-label">Not Started</span>
            <span class="stat-value">{{ data.projects_not_started ?? 0 }}</span>
          </div>
          <div class="stat-card waiting">
            <span class="stat-label">Waiting</span>
            <span class="stat-value">{{ data.projects_waiting ?? 0 }}</span>
          </div>
          <div class="stat-card completed">
            <span class="stat-label">Completed</span>
            <span class="stat-value">{{ data.projects_completed ?? 0 }}</span>
          </div>
        </div>
      </section>

      <!-- Total teams (admin) -->
      <section v-if="isAdminDashboard" class="section">
        <h2 class="section-title">Teams</h2>
        <div class="teams-total-card">
          <span class="stat-label">Total Teams</span>
          <span class="stat-value">{{ data.total_teams ?? 0 }}</span>
        </div>
      </section>

      <!-- Teams report table (admin) -->
      <section v-if="isAdminDashboard" class="section table-section">
        <h2 class="section-title">Team-wise report</h2>
        <div class="table-wrap">
          <table class="report-table" v-if="teamsSummary.length">
            <thead>
              <tr>
                <th>Team</th>
                <th>Members</th>
                <th>Projects assigned</th>
                <th>Projects completed</th>
                <th>Completed before deadline</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in teamsSummary" :key="row.id">
                <td class="cell-team">
                  <router-link :to="{ name: 'teams.show', params: { id: row.id } }" class="team-link">{{ row.name }}</router-link>
                </td>
                <td>{{ row.members_count ?? 0 }}</td>
                <td>{{ row.projects_assigned }}</td>
                <td>{{ row.projects_completed }}</td>
                <td>{{ row.projects_completed_before_deadline }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="empty-table">No teams yet.</p>
        </div>
      </section>

      <!-- Project Assignments: kon sa project kis ko assign (admin) -->
      <section v-if="isAdminDashboard && projectAssignments.length" class="section table-section">
        <h2 class="section-title">Project Assignments</h2>
        <p class="section-hint">Kon sa project kis member ko assign hai – PM, TL ya Employee level.</p>
        <div class="table-wrap">
          <table class="report-table">
            <thead>
              <tr>
                <th>Project</th>
                <th>Assigned To</th>
                <th>Level</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(a, idx) in projectAssignments" :key="idx">
                <td>
                  <router-link v-if="a.project_id" :to="`/projects/${a.project_id}`" class="team-link">{{ a.project_name || '-' }}</router-link>
                  <span v-else>{{ a.project_name || '-' }}</span>
                </td>
                <td>{{ a.assigned_to_name || '-' }}</td>
                <td><span class="level-badge">{{ a.assignment_level || '-' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Who is working today (admin) -->
      <section v-if="isAdminDashboard && whoWorkingToday.length" class="section table-section">
        <h2 class="section-title">Who is working today</h2>
        <p class="section-hint">Who submitted a daily plan today and which project they are working on.</p>
        <div class="table-wrap">
          <table class="report-table">
            <thead>
              <tr>
                <th>Member</th>
                <th>Plan Date</th>
                <th>Submitted At</th>
                <th>Projects (working on)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in whoWorkingToday" :key="idx">
                <td>{{ row.user_name || '-' }}</td>
                <td>{{ row.plan_date }}</td>
                <td>{{ row.submitted_at || '-' }}</td>
                <td>{{ (row.projects || []).join(', ') || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Other role stats (PM / TL / Employee) -->
      <section v-if="!isAdminDashboard && hasLegacyStats" class="section">
        <h2 class="section-title">Overview</h2>
        <div class="stats-grid legacy">
          <div v-for="(value, key) in legacyStats" :key="key" class="stat-card">
            <span class="stat-label">{{ formatKey(key) }}</span>
            <span class="stat-value">{{ formatValue(value, key) }}</span>
          </div>
        </div>
      </section>

      <!-- Employee: My Tasks box – tasks created, completed today, pending, ordered by due date -->
      <section v-if="employeeTasksSummary" class="section">
        <h2 class="section-title">My Tasks</h2>
        <div class="stats-row task-stats">
          <div class="stat-card">
            <span class="stat-label">Tasks I Created</span>
            <span class="stat-value">{{ data.tasks_created_count ?? 0 }}</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Completed Today</span>
            <span class="stat-value">{{ data.tasks_completed_today ?? 0 }}</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Pending</span>
            <span class="stat-value">{{ data.tasks_pending ?? 0 }}</span>
          </div>
        </div>
        <div class="table-wrap task-list-wrap">
          <p class="task-list-hint">Due soon at top</p>
          <table class="report-table" v-if="employeeTasksList.length">
            <thead>
              <tr>
                <th>Task</th>
                <th>Project</th>
                <th>Status</th>
                <th>Deadline</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in employeeTasksList" :key="t.id">
                <td class="cell-task">
                  <router-link :to="`/tasks/${t.id}`" class="team-link">{{ t.name }}</router-link>
                </td>
                <td>
                  <router-link v-if="t.project" :to="`/projects/${t.project.id}`" class="project-link">{{ t.project.name }}</router-link>
                  <span v-else>-</span>
                </td>
                <td><span :class="['task-status-badge', t.status]">{{ formatTaskStatus(t.status) }}</span></td>
                <td>{{ t.deadline ? formatDeadline(t.deadline) : '-' }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="empty-table">No tasks yet.</p>
        </div>
      </section>

      <!-- TL: assigned projects list -->
      <section v-if="assignedProjectsList.length" class="section table-section">
        <h2 class="section-title">Assigned Projects</h2>
        <div class="table-wrap">
          <table class="report-table">
            <thead>
              <tr>
                <th>Project</th>
                <th>Status</th>
                <th>Assigned At</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in assignedProjectsList" :key="p.id">
                <td class="cell-team">
                  <router-link :to="`/projects/${p.id}`" class="team-link">{{ p.name }}</router-link>
                </td>
                <td>{{ p.status }}</td>
                <td>{{ p.assigned_at }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { analyticsService } from '../../services/analyticsService'
import { attendanceService } from '../../services/attendanceService'
import { leaveService } from '../../services/leaveService'
import { useAuthStore } from '../../stores/auth'
import { useToast } from '../../stores/toast'
import { getErrorMessage } from '../../utils/errorMessage'

const authStore = useAuthStore()
const toast = useToast()
const isAdmin = computed(() => authStore.hasRole('admin'))
const isAdminOrHR = computed(() => authStore.hasAnyRole(['admin', 'hr']))
const leaveDashboard = ref(null)
const leaveDashboardLoading = ref(false)

const data = ref(null)
const loading = ref(false)
const error = ref('')

const todayAttendance = ref(null)
const attendanceLoading = ref(false)
const attendanceActionLoading = ref(false)

const todayLabel = computed(() => {
  const d = new Date()
  return d.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' })
})

const formatTime = (dateStr) => {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

// Today's work hours: from check-in to check-out (display correctly even if backend sends 0)
const formatShiftHours = (att) => {
  let totalM = (att.regular_minutes ?? 0) + (att.overtime_minutes ?? 0) || (att.total_work_minutes ?? 0)
  if (totalM <= 0 && att.check_in_time && att.check_out_time) {
    const start = new Date(att.check_in_time).getTime()
    const end = new Date(att.check_out_time).getTime()
    totalM = Math.max(0, Math.floor((end - start) / 60000))
  }
  if (totalM <= 0) return '0h 0m'
  const h = Math.floor(totalM / 60)
  const m = totalM % 60
  const parts = []
  if (h) parts.push(`${h}h`)
  if (m) parts.push(`${m}m`)
  return parts.length ? parts.join(' ') : '0h 0m'
}

const loadTodayAttendance = async () => {
  attendanceLoading.value = true
  try {
    const res = await attendanceService.getToday()
    if (res.data.success) todayAttendance.value = res.data.data
    else todayAttendance.value = null
  } catch {
    todayAttendance.value = null
  } finally {
    attendanceLoading.value = false
  }
}

const doCheckIn = async () => {
  attendanceActionLoading.value = true
  try {
    const res = await attendanceService.checkIn()
    if (res.data.success) {
      todayAttendance.value = res.data.data
    } else {
      toast.showError(res.data.message || 'Check-in failed')
    }
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Check-in failed'))
  } finally {
    attendanceActionLoading.value = false
  }
}

const doCheckOut = async () => {
  attendanceActionLoading.value = true
  try {
    const res = await attendanceService.checkOut()
    if (res.data.success) {
      todayAttendance.value = res.data.data
    } else {
      toast.showError(res.data.message || 'Check-out failed')
    }
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Check-out failed'))
  } finally {
    attendanceActionLoading.value = false
  }
}

const teamsSummary = computed(() => {
  if (!data.value?.teams_summary) return []
  return data.value.teams_summary
})

const projectAssignments = computed(() => {
  if (!data.value?.project_assignments) return []
  return data.value.project_assignments
})

const whoWorkingToday = computed(() => {
  if (!data.value?.who_working_today) return []
  return data.value.who_working_today
})

const isAdminDashboard = computed(() => {
  return data.value && typeof data.value.total_projects === 'number'
})

const hasLegacyStats = computed(() => {
  if (!data.value) return false
  const exclude = ['teams_summary', 'total_projects', 'projects_working', 'projects_not_started', 'projects_waiting', 'projects_completed', 'total_teams', 'tasks_created_count', 'tasks_completed_today', 'tasks_pending', 'my_tasks_list', 'project_assignments', 'who_working_today']
  const keys = Object.keys(data.value).filter(k => !exclude.includes(k))
  return keys.length > 0
})

const legacyStats = computed(() => {
  if (!data.value) return {}
  const exclude = ['teams_summary', 'total_projects', 'projects_working', 'projects_not_started', 'projects_waiting', 'projects_completed', 'total_teams', 'tasks_created_count', 'tasks_completed_today', 'tasks_pending', 'my_tasks_list', 'project_assignments', 'who_working_today']
  return Object.fromEntries(Object.entries(data.value).filter(([k]) => !exclude.includes(k)))
})

const employeeTasksSummary = computed(() => {
  return data.value && (typeof data.value.tasks_created_count === 'number' || Array.isArray(data.value.my_tasks_list))
})

const employeeTasksList = computed(() => {
  const list = data.value?.my_tasks_list
  return Array.isArray(list) ? list : []
})

const formatTaskStatus = (s) => {
  if (!s) return ''
  return String(s).replace(/_/g, ' ')
}

const formatDeadline = (d) => {
  if (!d) return '-'
  const dt = new Date(d)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const deadline = new Date(dt)
  deadline.setHours(0, 0, 0, 0)
  const diff = Math.ceil((deadline - today) / (1000 * 60 * 60 * 24))
  if (diff < 0) return `${d} (Overdue)`
  if (diff === 0) return `${d} (Today)`
  if (diff === 1) return `${d} (Tomorrow)`
  if (diff <= 7) return `${d} (in ${diff} days)`
  return d
}

const assignedProjectsList = computed(() => {
  const list = data.value?.assigned_projects_list
  return Array.isArray(list) ? list : []
})

const loadDashboard = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await analyticsService.getDashboard()
    if (response.data.success) {
      data.value = response.data.data
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load dashboard'
  } finally {
    loading.value = false
  }
}

const formatKey = (key) => {
  return key.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
}

const formatValue = (value, key) => {
  if (key.includes('rate') || key.includes('percentage')) return `${value}%`
  if (key.includes('hours')) return `${value}h`
  return value
}

const loadLeaveDashboard = async () => {
  if (!isAdminOrHR.value) return
  leaveDashboardLoading.value = true
  try {
    const res = await leaveService.getDashboard()
    if (res.data.success) leaveDashboard.value = res.data.data
  } catch { leaveDashboard.value = null }
  finally { leaveDashboardLoading.value = false }
}

onMounted(() => {
  loadTodayAttendance()
  loadDashboard()
  loadLeaveDashboard()
})
</script>

<style scoped>
.dashboard {
  max-width: 1200px;
  margin: 0 auto;
}

.dashboard-header {
  margin-bottom: 28px;
}

.dashboard-header h1 {
  font-size: 1.875rem;
  font-weight: 700;
  color: #2c2c2c;
  margin: 0 0 6px 0;
  letter-spacing: -0.02em;
}

.dashboard-subtitle {
  font-size: 0.9375rem;
  color: #5a5a5a;
  margin: 0;
}

.attendance-section {
  margin-bottom: 28px;
}

.attendance-card {
  background: #fff;
  border-radius: 10px;
  padding: 24px 28px;
  border: 1px solid #d8dce0;
  border-left: 4px solid #265b99;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}

.attendance-loading {
  color: #666;
}

.attendance-status {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.attendance-date {
  font-size: 0.9rem;
  color: #555;
  font-weight: 600;
}

.attendance-time {
  margin: 0;
  font-size: 0.95rem;
  color: #2c2c2c;
}

.attendance-time .shift-hrs {
  color: #265b99;
  font-size: 0.9rem;
}

.attendance-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-check-in {
  padding: 12px 24px;
  background: #198754;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
}

.btn-check-in:hover:not(:disabled) {
  background: #157347;
}

.btn-check-out {
  padding: 12px 24px;
  background: #dc3545;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
}

.btn-check-out:hover:not(:disabled) {
  background: #bb2d3b;
}

.btn-check-in:disabled,
.btn-check-out:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.attendance-done {
  font-size: 0.95rem;
  color: #155724;
  font-weight: 500;
}

.btn-my-attendance {
  padding: 10px 18px;
  background: #265b99;
  color: #fff;
  text-decoration: none;
  border-radius: 8px;
  font-weight: 500;
  font-size: 0.9rem;
}

.btn-my-attendance:hover {
  background: #1e4a7a;
}

.leave-section { margin-bottom: 28px; }
.leave-loading { color: #666; padding: 12px 0; }
.leave-dashboard-card {
  background: #fff;
  border-radius: 10px;
  padding: 20px 24px;
  border: 1px solid #d8dce0;
  border-left: 4px solid #265b99;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.leave-hint { font-size: 0.9rem; color: #555; margin: 0 0 16px 0; }
.leave-stats { display: flex; gap: 20px; margin-bottom: 16px; flex-wrap: wrap; }
.leave-stat { padding: 12px 20px; border-radius: 8px; min-width: 100px; }
.leave-stat.pending { background: #fffbf0; border-left: 4px solid #ffc107; }
.leave-stat.approved { background: #f0f9f4; border-left: 4px solid #198754; }
.leave-stat.rejected { background: #fef5f5; border-left: 4px solid #dc3545; }
.leave-stat-label { font-size: 0.75rem; color: #555; display: block; }
.leave-stat-value { font-size: 1.5rem; font-weight: 700; color: #333; }
.btn-leave-requests { display: inline-block; padding: 10px 18px; background: #265b99; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 500; }
.btn-leave-requests:hover { background: #1e4a7a; }

.section {
  margin-bottom: 36px;
}

.section-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #404040;
  margin: 0 0 16px 0;
  padding: 0 0 10px 12px;
  border-left: 4px solid #265b99;
  border-bottom: 1px solid #d8dce0;
  background: linear-gradient(90deg, rgba(38, 91, 153, 0.06) 0%, transparent 100%);
  border-radius: 0 0 6px 0;
}

.section-hint {
  font-size: 0.9rem;
  color: #555;
  margin: 0 0 12px 0;
}

.level-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 6px;
  background: #e8eef5;
  color: #265b99;
  font-size: 0.85rem;
  font-weight: 500;
}

.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 18px;
}

.stat-card {
  background: #fff;
  border-radius: 10px;
  padding: 22px 20px;
  border: 1px solid #d8dce0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
}

.stat-card:hover {
  border-color: #265b99;
  box-shadow: 0 6px 16px rgba(38, 91, 153, 0.15);
  transform: translateY(-2px);
}

.stat-card.total { border-left: 4px solid #404040; }
.stat-card.working { border-left: 4px solid #265b99; }
.stat-card.not-started { border-left: 4px solid #6b7280; }
.stat-card.waiting { border-left: 4px solid #5a5a5a; }
.stat-card.completed { border-left: 4px solid #265b99; }

.stat-label {
  font-size: 0.7rem;
  font-weight: 700;
  color: #5a5a5a;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.stat-value {
  font-size: 1.875rem;
  font-weight: 800;
  color: #265b99;
  letter-spacing: -0.02em;
}

.stat-card.total .stat-value { color: #404040; }

.teams-total-card {
  background: #fff;
  border-radius: 10px;
  padding: 26px 24px;
  border: 1px solid #d8dce0;
  border-left: 4px solid #265b99;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  max-width: 280px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.teams-total-card .stat-value {
  font-size: 2.25rem;
  color: #265b99;
}

.table-section .table-wrap {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #d8dce0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  overflow: hidden;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9375rem;
}

.report-table thead {
  background: #404040;
  color: #fff;
}

.report-table th {
  text-align: left;
  padding: 14px 18px;
  font-weight: 600;
  font-size: 0.8125rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: none;
}

.report-table td {
  padding: 14px 18px;
  border-bottom: 1px solid #e8eaed;
  color: #2c2c2c;
}

.report-table tbody tr:hover {
  background: rgba(38, 91, 153, 0.06);
}

.cell-team {
  font-weight: 500;
}

.team-link {
  color: #265b99;
  text-decoration: none;
}

.team-link:hover {
  text-decoration: underline;
}

.empty-table {
  padding: 32px;
  text-align: center;
  color: #555;
  margin: 0;
}

.stats-grid.legacy {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.task-stats {
  margin-bottom: 20px;
}

.task-list-wrap {
  margin-top: 8px;
}

.task-list-hint {
  font-size: 0.8rem;
  color: #666;
  margin: 0 0 10px 0;
}

.cell-task {
  font-weight: 500;
}

.project-link {
  color: #265b99;
  text-decoration: none;
}

.project-link:hover {
  text-decoration: underline;
}

.task-status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: capitalize;
}

.task-status-badge.todo { background: #e8e8e8; color: #333; }
.task-status-badge.in_progress { background: #cce5ff; color: #004085; }
.task-status-badge.review { background: #fff3cd; color: #856404; }
.task-status-badge.completed { background: #d4edda; color: #155724; }
.task-status-badge.cancelled { background: #f8d7da; color: #721c24; }

.btn-task-edit {
  padding: 6px 12px;
  background: #265b99;
  color: white;
  text-decoration: none;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 500;
}

.btn-task-edit:hover {
  background: #1e4a7a;
}

.loading, .error {
  text-align: center;
  padding: 40px;
  color: #555;
}

.error {
  color: #333;
  background: #eee;
  border-radius: 8px;
  margin: 0 0 24px 0;
}

@media (max-width: 768px) {
  .stats-row {
    grid-template-columns: repeat(2, 1fr);
  }

  .table-wrap {
    overflow-x: auto;
  }

  .report-table {
    min-width: 480px;
  }
}
</style>
