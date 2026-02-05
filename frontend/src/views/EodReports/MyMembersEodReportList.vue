<template>
  <div class="eod-report-list">
    <div class="header">
      <h1>My Members EODs</h1>
      <router-link to="/eod-reports" class="btn-secondary">My EOD Reports</router-link>
    </div>

    <div class="filters">
      <input
        type="date"
        v-model="filters.date"
        @change="loadReports"
        class="filter-input"
      />
      <select v-model="filters.status" @change="loadReports" class="filter-select">
        <option value="">All Status</option>
        <option value="draft">Draft</option>
        <option value="submitted">Submitted</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
    </div>

    <div v-if="loading" class="loading">Loading EOD reports...</div>
    <div v-else-if="error" class="error">{{ error }}</div>

    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Member</th>
            <th>Date</th>
            <th>Items</th>
            <th>Status</th>
            <th>Submitted At</th>
            <th>Approved At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="report in reports" :key="report.id">
            <td>{{ report.user?.name || '-' }}</td>
            <td>{{ report.date }}</td>
            <td>{{ report.items?.length || 0 }} item(s)</td>
            <td>
              <span :class="['status-badge', report.status]">{{ report.status }}</span>
            </td>
            <td>{{ report.submitted_at || '-' }}</td>
            <td>{{ report.approved_at || '-' }}</td>
            <td class="actions">
              <button @click="viewReport(report.id)" class="btn-view">View</button>
              <button v-if="report.status === 'submitted'" @click="approveReport(report)" class="btn-approve">Approve</button>
              <button v-if="report.status === 'submitted'" @click="rejectReport(report)" class="btn-reject">Reject</button>
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
import { useRouter } from 'vue-router'
import { eodReportService } from '../../services/eodReportService'
import { approvalService } from '../../services/approvalService'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'

const router = useRouter()
const toast = useToast()
const confirmStore = useConfirm()
const reports = ref([])
const meta = ref(null)
const loading = ref(false)
const error = ref('')
const filters = ref({ date: '', status: '' })

const loadReports = async () => {
  loading.value = true
  error.value = ''
  try {
    const params = { per_page: 15, ...filters.value }
    Object.keys(params).forEach(k => { if (params[k] === '') delete params[k] })
    const res = await eodReportService.getMyMembers(params)
    if (res.data.success) {
      reports.value = res.data.data || []
      meta.value = res.data.meta || null
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load reports'
  } finally {
    loading.value = false
  }
}

const changePage = (page) => {
  filters.value.page = page
  loadReports()
}

const viewReport = (id) => {
  router.push({ path: `/eod-reports/${id}`, query: { from: 'my-members' } })
}

const approveReport = async (report) => {
  const ok = await confirmStore.confirm('Approve EOD', `Approve EOD of ${report.user?.name || 'member'} for ${report.date}? Have you checked the tasks they listed?`)
  if (!ok) return
  try {
    await approvalService.approveReport(report.id)
    loadReports()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Approve nahi hua.'))
  }
}

const rejectReport = async (report) => {
  const comment = prompt('Reject reason (optional):')
  if (comment === null) return
  try {
    await approvalService.rejectReport(report.id, comment || '')
    loadReports()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Reject failed.'))
  }
}

onMounted(() => loadReports())
</script>

<style scoped>
.eod-report-list { padding: 20px; max-width: 1400px; margin: 0 auto; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; gap: 12px; }
.header h1 { color: #333; font-size: 2rem; margin: 0; }
.btn-secondary { padding: 10px 14px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 5px; font-weight: 600; }
.filters { display: flex; gap: 15px; margin-bottom: 20px; }
.filter-input, .filter-select { padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
.table-container { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead { background: #f5f5f5; }
.data-table th { padding: 15px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #ddd; }
.data-table td { padding: 15px; border-bottom: 1px solid #eee; }
.status-badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; text-transform: capitalize; background: #e8e8e8; color: #333; }
.status-badge.approved { background: #d4edda; color: #155724; }
.status-badge.rejected { background: #f8d7da; color: #721c24; }
.actions { display: flex; gap: 8px; }
.btn-view { padding: 6px 12px; background: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-view:hover { background: #0b5ed7; }
.btn-approve { padding: 6px 12px; background: #198754; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-approve:hover { background: #157347; }
.btn-reject { padding: 6px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-reject:hover { background: #bb2d3b; }
.pagination { display: flex; justify-content: center; align-items: center; gap: 15px; padding: 20px; }
.pagination button { padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer; }
.pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
.loading, .error { text-align: center; padding: 40px; color: #666; }
.error { color: #333; }
</style>

