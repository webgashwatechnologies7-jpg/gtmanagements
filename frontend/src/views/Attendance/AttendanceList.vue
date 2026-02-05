<template>
  <div class="attendance-list">
    <div class="header">
      <h1>{{ isAdmin ? 'Attendance (All)' : 'My Attendance' }}</h1>
    </div>

    <!-- Month summary: Present, Absent, Total hours (is range ke saare din ka total) -->
    <section v-if="!loading && statistics" class="summary-section">
      <h2 class="summary-title">Summary ({{ summaryMonthLabel }})</h2>
      <p class="summary-hint">Total for all days in this date range – daily working hours are summed here.</p>
      <div class="summary-cards">
        <div class="summary-card present">
          <span class="summary-label">Present</span>
          <span class="summary-value">{{ statistics.present ?? 0 }}</span>
        </div>
        <div class="summary-card absent">
          <span class="summary-label">Absent</span>
          <span class="summary-value">{{ statistics.absent ?? 0 }}</span>
        </div>
        <div class="summary-card hours">
          <span class="summary-label">Regular Hours (total)</span>
          <span class="summary-value">{{ formatSummaryMinutes(statistics.total_regular_minutes ?? 0) }}</span>
        </div>
        <div class="summary-card overtime">
          <span class="summary-label">OT Hours (total)</span>
          <span class="summary-value">{{ formatSummaryMinutes(statistics.total_overtime_minutes ?? 0) }}</span>
        </div>
      </div>
    </section>

    <!-- Filters -->
    <div class="filters">
      <select v-if="isAdmin" v-model="filters.user_id" @change="onFilterChange" class="filter-select">
        <option value="">All Members</option>
        <option v-for="u in memberList" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
      <input
        type="date"
        v-model="filters.date_from"
        @change="onFilterChange"
        class="filter-input"
        title="From date"
      />
      <input
        type="date"
        v-model="filters.date_to"
        @change="onFilterChange"
        class="filter-input"
        title="To date"
      />
      <select v-model="filters.status" @change="onFilterChange" class="filter-select">
        <option value="">All Status</option>
        <option value="present">Present</option>
        <option value="absent">Absent</option>
        <option value="holiday">Holiday</option>
        <option value="leave">Leave</option>
        <option value="half_day">Half Day</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading attendance...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Attendance Table -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>User</th>
            <th>Status</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Working hours</th>
            <th>OT hours</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="attendance in (attendances || [])" :key="attendance.id">
            <td>{{ attendance.date }}</td>
            <td>{{ attendance.user?.name || '-' }}</td>
            <td>
              <span :class="['status-badge', attendance.status]">
                {{ attendance.status }}
              </span>
            </td>
            <td>{{ formatDateTime(attendance.check_in_time) }}</td>
            <td>{{ formatDateTime(attendance.check_out_time) }}</td>
            <td>{{ formatWorkingHours(attendance) }}</td>
            <td>{{ formatOTHours(attendance) }}</td>
            <td class="actions">
              <button v-if="canEditDelete" type="button" class="btn-edit btn-disabled" title="Coming soon" disabled>Edit</button>
              <button v-if="canEditDelete" @click="deleteAttendance(attendance.id)" class="btn-delete">Delete</button>
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
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { attendanceService } from '../../services/attendanceService'
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
// Only Admin or HR can edit/delete; others can only view
const canEditDelete = computed(() => authStore.hasAnyRole(['admin', 'hr']))
const memberList = ref([])

const attendances = ref([])
const loading = ref(false)
const error = ref('')
const statistics = ref(null)
const filters = ref({
  user_id: '',
  date_from: getFirstDayOfMonth(),
  date_to: getLastDayOfMonth(),
  status: '',
  page: 1
})
const meta = ref(null)

function getFirstDayOfMonth() {
  const d = new Date()
  d.setDate(1)
  return d.toISOString().split('T')[0]
}
function getLastDayOfMonth() {
  const d = new Date()
  d.setMonth(d.getMonth() + 1)
  d.setDate(0)
  return d.toISOString().split('T')[0]
}

const summaryMonthLabel = (() => {
  const d = new Date()
  return d.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })
})()

const formatDateTime = (dateStr) => {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleString('en-IN', { dateStyle: 'short', timeStyle: 'short' })
}

// Summary me simple format: 2m, 1h 5m, 8h (0.03h ki jagah "2m")
const formatSummaryMinutes = (totalMinutes) => {
  const m = Math.round(Number(totalMinutes) || 0)
  if (m <= 0) return '0m'
  const h = Math.floor(m / 60)
  const min = m % 60
  if (h && min) return `${h}h ${min}m`
  if (h) return `${h}h`
  return `${min}m`
}

// Day's regular shift: max 8 hours (480 min). Derived from check-in/check-out if backend sends 0.
const getShiftMinutes = (att) => {
  let regM = att.regular_minutes ?? 0
  let otM = att.overtime_minutes ?? 0
  if (regM === 0 && otM === 0 && att.check_in_time && att.check_out_time) {
    const start = new Date(att.check_in_time).getTime()
    const end = new Date(att.check_out_time).getTime()
    const totalM = Math.max(0, Math.floor((end - start) / 60000))
    regM = Math.min(480, totalM)
    otM = Math.max(0, totalM - 480)
  }
  return { regM, otM }
}

const formatWorkingHours = (att) => {
  const { regM } = getShiftMinutes(att)
  if (regM <= 0) return '-'
  const h = Math.floor(regM / 60)
  const m = regM % 60
  if (h) return m ? `${h}h ${m}m` : `${h}h`
  return `${m}m`
}

// OT hours: only shown when there is overtime (more than 8h work in a day)
const formatOTHours = (att) => {
  const { otM } = getShiftMinutes(att)
  if (otM <= 0) return '-'
  const h = Math.floor(otM / 60)
  const m = otM % 60
  if (h) return m ? `${h}h ${m}m` : `${h}h`
  return `${m}m`
}

const loadStatistics = async () => {
  try {
    const params = { date_from: filters.value.date_from, date_to: filters.value.date_to }
    if (filters.value.user_id) params.user_id = filters.value.user_id
    const res = await attendanceService.getStatistics(params)
    if (res.data.success) statistics.value = res.data.data
  } catch {
    statistics.value = null
  }
}

const loadAttendance = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      per_page: 15,
      page: filters.value.page,
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
      status: filters.value.status
    }
    if (filters.value.user_id) params.user_id = filters.value.user_id
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null) delete params[key]
    })

    const response = await attendanceService.getAll(params)
    if (response.data.success) {
      attendances.value = response.data.data
      meta.value = response.data.meta
    }
    await loadStatistics()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load attendance'
  } finally {
    loading.value = false
  }
}

const onFilterChange = () => {
  filters.value.page = 1
  loadAttendance()
}

const changePage = (page) => {
  filters.value.page = page
  loadAttendance()
}

// Edit: coming soon – button disabled until implementation

const deleteAttendance = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to delete this attendance record?')
  if (!ok) return
  try {
    await attendanceService.delete(id)
    loadAttendance()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to delete attendance'))
  }
}

const loadMembers = async () => {
  if (!isAdmin.value) return
  try {
    const res = await userService.getAll({ per_page: 200 })
    if (res.data.success && Array.isArray(res.data.data)) {
      memberList.value = res.data.data
    }
  } catch { memberList.value = [] }
}

onMounted(() => {
  loadMembers()
  loadAttendance()
})
</script>

<style scoped>
.attendance-list {
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

.summary-section {
  margin-bottom: 24px;
}

.summary-title {
  font-size: 1rem;
  font-weight: 600;
  color: #404040;
  margin: 0 0 4px 0;
}

.summary-hint {
  font-size: 0.85rem;
  color: #666;
  margin: 0 0 12px 0;
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 16px;
}

.summary-card {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  border: 1px solid #d8dce0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.summary-card.present { border-left: 4px solid #198754; }
.summary-card.absent { border-left: 4px solid #dc3545; }
.summary-card.hours { border-left: 4px solid #265b99; }
.summary-card.overtime { border-left: 4px solid #fd7e14; }

.summary-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #555;
  text-transform: uppercase;
}

.summary-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #333;
}

.filters {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
}

.filter-input,
.filter-select {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
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

.status-badge.present {
  background: #e0e0e0;
  color: #333;
}

.status-badge.absent {
  background: #ddd;
  color: #333;
}

.status-badge.holiday {
  background: #e0e0e0;
  color: #333;
}

.status-badge.leave {
  background: #e8e8e8;
  color: #333;
}

.status-badge.half_day {
  background: #e2e3e5;
  color: #383d41;
}

.actions {
  display: flex;
  gap: 10px;
}

.btn-edit.btn-disabled {
  opacity: 0.6;
  cursor: not-allowed;
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

.btn-delete {
  padding: 6px 12px;
  background: #333;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
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
