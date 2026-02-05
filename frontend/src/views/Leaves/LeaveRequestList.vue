<template>
  <div class="leave-request-list">
    <div class="header">
      <h1>Leave Requests</h1>
      <p class="subtitle">Who has applied for leave, which are approved/rejected – only Admin/HR can approve.</p>
      <router-link to="/leaves" class="btn-back">My Leave</router-link>
    </div>

    <!-- Summary cards -->
    <section v-if="dashboard" class="summary-section">
      <div class="summary-cards">
        <div class="card pending">
          <span class="label">Pending</span>
          <span class="value">{{ dashboard.pending_count ?? 0 }}</span>
        </div>
        <div class="card approved">
          <span class="label">Approved</span>
          <span class="value">{{ dashboard.approved_count ?? 0 }}</span>
        </div>
        <div class="card rejected">
          <span class="label">Rejected</span>
          <span class="value">{{ dashboard.rejected_count ?? 0 }}</span>
        </div>
      </div>
    </section>

    <div class="filters">
      <select v-model="filters.status" @change="loadLeaves" class="filter-select">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
    </div>

    <div v-if="loading" class="loading">Loading...</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Employee</th>
            <th>From</th>
            <th>To</th>
            <th>Type</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Approved/Rejected At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="leave in (leaves || [])" :key="leave.id">
            <td>{{ leave.user?.name || '-' }} <span class="email">{{ leave.user?.email }}</span></td>
            <td>{{ leave.date_from }}</td>
            <td>{{ leave.date_to }}</td>
            <td>{{ leave.leave_type || '-' }}</td>
            <td>{{ leave.reason ? (leave.reason.slice(0, 35) + (leave.reason.length > 35 ? '...' : '')) : '-' }}</td>
            <td>
              <span :class="['status-badge', leave.status]">{{ leave.status }}</span>
            </td>
            <td>{{ leave.approved_at || '-' }}</td>
            <td class="actions">
              <button v-if="leave.status === 'pending'" @click="approveLeave(leave)" class="btn-approve" :disabled="actionLoading">Approve</button>
              <button v-if="leave.status === 'pending'" @click="rejectLeave(leave)" class="btn-reject" :disabled="actionLoading">Reject</button>
              <span v-else class="done">–</span>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="meta && meta.last_page > 1" class="pagination">
        <button @click="changePage(meta.current_page - 1)" :disabled="meta.current_page === 1">Previous</button>
        <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button @click="changePage(meta.current_page + 1)" :disabled="meta.current_page === meta.last_page">Next</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { leaveService } from '../../services/leaveService'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'

const toast = useToast()
const confirmStore = useConfirm()
const leaves = ref([])
const dashboard = ref(null)
const loading = ref(false)
const error = ref('')
const actionLoading = ref(false)
const filters = ref({ status: '', page: 1 })
const meta = ref(null)

const loadDashboard = async () => {
  try {
    const res = await leaveService.getDashboard()
    if (res.data.success) dashboard.value = res.data.data
  } catch { dashboard.value = null }
}

const loadLeaves = async () => {
  loading.value = true
  error.value = ''
  try {
    const params = { per_page: 15, ...filters.value }
    Object.keys(params).forEach(k => { if (params[k] === '') delete params[k] })
    const res = await leaveService.getAll(params)
    if (res.data.success) {
      leaves.value = res.data.data || []
      meta.value = res.data.meta || null
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load leave requests'
  } finally {
    loading.value = false
  }
}

const changePage = (p) => {
  filters.value.page = p
  loadLeaves()
}

const approveLeave = async (leave) => {
  const ok = await confirmStore.confirm('Approve leave', `Approve leave for ${leave.user?.name} (${leave.date_from} to ${leave.date_to})?`)
  if (!ok) return
  actionLoading.value = true
  try {
    await leaveService.approve(leave.id)
    loadLeaves()
    loadDashboard()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Approve failed'))
  } finally {
    actionLoading.value = false
  }
}

const rejectLeave = async (leave) => {
  const reason = prompt('Rejection reason (optional):')
  if (reason === null) return
  actionLoading.value = true
  try {
    await leaveService.reject(leave.id, reason || '')
    loadLeaves()
    loadDashboard()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Reject failed'))
  } finally {
    actionLoading.value = false
  }
}

onMounted(() => {
  loadDashboard()
  loadLeaves()
})
</script>

<style scoped>
.leave-request-list { padding: 20px; max-width: 1200px; margin: 0 auto; }
.header { margin-bottom: 24px; }
.header h1 { margin: 0 0 6px 0; color: #333; font-size: 1.75rem; }
.subtitle { margin: 0 0 12px 0; color: #555; font-size: 0.95rem; }
.btn-back { display: inline-block; padding: 10px 18px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; }
.summary-section { margin-bottom: 24px; }
.summary-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; max-width: 400px; }
.summary-cards .card { padding: 16px; border-radius: 8px; border-left: 4px solid #ccc; }
.summary-cards .card.pending { border-color: #ffc107; background: #fffbf0; }
.summary-cards .card.approved { border-color: #198754; background: #f0f9f4; }
.summary-cards .card.rejected { border-color: #dc3545; background: #fef5f5; }
.summary-cards .label { font-size: 0.8rem; color: #555; display: block; }
.summary-cards .value { font-size: 1.5rem; font-weight: 700; color: #333; }
.filters { margin-bottom: 16px; }
.filter-select { padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
.table-container { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 14px; text-align: left; font-weight: 600; background: #f5f5f5; color: #333; }
.data-table td { padding: 14px; border-bottom: 1px solid #eee; }
.email { font-size: 0.8rem; color: #666; display: block; }
.status-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; text-transform: capitalize; }
.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.approved { background: #d4edda; color: #155724; }
.status-badge.rejected { background: #f8d7da; color: #721c24; }
.actions { display: flex; gap: 8px; }
.btn-approve { padding: 6px 12px; background: #198754; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-reject { padding: 6px 12px; background: #dc3545; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-approve:disabled, .btn-reject:disabled { opacity: 0.6; cursor: not-allowed; }
.done { color: #999; }
.pagination { display: flex; justify-content: center; align-items: center; gap: 12px; padding: 16px; }
.pagination button { padding: 8px 14px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer; }
.loading, .error { padding: 40px; text-align: center; color: #666; }
.error { color: #c00; }
</style>
