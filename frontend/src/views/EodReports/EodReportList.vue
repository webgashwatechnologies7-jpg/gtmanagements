<template>
  <div class="eod-report-list">
    <div class="header">
      <h1>EOD Reports</h1>
    </div>

    <!-- Filters -->
    <div class="filters">
      <select v-if="isAdmin" v-model="filters.user_id" @change="loadReports" class="filter-select">
        <option value="">All Members</option>
        <option v-for="u in memberList" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
      <input
        type="date"
        v-model="filters.date"
        @change="loadReports"
        class="filter-input"
      />
      <select v-model="filters.status" @change="loadReports" class="filter-select">
        <option value="">All Status</option>
        <option value="draft">Draft</option>
        <option value="submitted">Pending approval</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
      <button v-if="isAdmin" type="button" class="btn-pending" :class="{ active: filters.status === 'submitted' }" @click="setPendingFilter">
        Pending approval only
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading EOD reports...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Reports Table -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th v-if="isAdmin">Member</th>
            <th v-if="isAdmin">Team</th>
            <th>Date</th>
            <th>Items</th>
            <th>Status</th>
            <th>Submitted At</th>
            <th>Approved At</th>
            <th v-if="isAdmin">Approved By</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="report in (reports || [])" :key="report.id">
            <td v-if="isAdmin">{{ report.user?.name || '-' }}</td>
            <td v-if="isAdmin">{{ userTeamNames(report.user) }}</td>
            <td>{{ report.date }}</td>
            <td>{{ report.items?.length || 0 }} item(s)</td>
            <td>
              <span :class="['status-badge', report.status]">
                {{ report.status }}
              </span>
            </td>
            <td>{{ report.submitted_at || '-' }}</td>
            <td>{{ report.approved_at || '-' }}</td>
            <td v-if="isAdmin">{{ report.approver?.name || '-' }}</td>
            <td class="actions">
              <button @click="viewReport(report.id)" class="btn-view">View</button>
              <button v-if="report.status === 'draft' || report.status === 'rejected'" @click="editReport(report.id)" class="btn-edit">Edit</button>
              <button v-if="report.status === 'draft'" @click="submitReport(report.id)" class="btn-submit">Submit</button>
              <button v-if="report.status === 'draft' || report.status === 'rejected'" @click="deleteReport(report.id)" class="btn-delete">Delete</button>
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
import { eodReportService } from '../../services/eodReportService'
import { userService } from '../../services/userService'
import { useAuthStore } from '../../stores/auth'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'

const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()
const confirmStore = useConfirm()
const isTL = computed(() => authStore.hasAnyRole(['team_lead', 'team_leader']))
const isAdmin = computed(() => authStore.hasRole('admin'))
const memberList = ref([])

const reports = ref([])
const loading = ref(false)
const error = ref('')
const filters = ref({
  user_id: '',
  date: '',
  status: '',
  page: 1
})
const meta = ref(null)

const setPendingFilter = () => {
  filters.value.status = filters.value.status === 'submitted' ? '' : 'submitted'
  loadReports()
}

const userTeamNames = (user) => {
  if (!user?.teams?.length) return '-'
  return user.teams.map(t => t.name).join(', ')
}

const loadReports = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      per_page: 15,
      page: filters.value.page,
      date: filters.value.date,
      status: filters.value.status
    }
    if (filters.value.user_id) params.user_id = filters.value.user_id
    // TL: only own EODs
    if (isTL.value && !isAdmin.value && authStore.user?.id) {
      params.user_id = authStore.user.id
    }

    Object.keys(params).forEach(key => {
      if (params[key] === '') delete params[key]
    })

    const response = await eodReportService.getAll(params)
    if (response.data.success) {
      reports.value = response.data.data
      meta.value = response.data.meta
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load EOD reports'
  } finally {
    loading.value = false
  }
}

const changePage = (page) => {
  filters.value.page = page
  loadReports()
}

const viewReport = (id) => {
  router.push(`/eod-reports/${id}`)
}

const editReport = (id) => {
  router.push(`/eod-reports/${id}/edit`)
}

const submitReport = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to submit this EOD report?')
  if (!ok) return
  try {
    await eodReportService.submit(id)
    loadReports()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to submit report'))
  }
}

const deleteReport = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to delete this EOD report?')
  if (!ok) return
  try {
    await eodReportService.delete(id)
    loadReports()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to delete report'))
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
  loadReports()
})
</script>

<style scoped>
.eod-report-list {
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

.filters {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
}

.filter-input,
.btn-pending {
  padding: 10px 16px;
  border: 1px solid #ffc107;
  border-radius: 5px;
  background: #fffbf0;
  color: #856404;
  font-size: 14px;
  cursor: pointer;
}
.btn-pending:hover, .btn-pending.active {
  background: #ffc107;
  color: #1a1a1a;
}

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

.status-badge.draft {
  background: #e8e8e8;
  color: #333;
}

.status-badge.submitted {
  background: #e0e0e0;
  color: #333;
}

.status-badge.approved {
  background: #e0e0e0;
  color: #333;
}

.status-badge.rejected {
  background: #ddd;
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
