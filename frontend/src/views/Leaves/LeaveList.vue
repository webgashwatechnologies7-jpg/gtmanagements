<template>
  <div class="leave-list">
    <div class="header">
      <h1>My Leave</h1>
      <div class="header-actions">
        <router-link to="/leaves/apply" class="btn-primary">Apply for Leave</router-link>
        <router-link v-if="canManageLeaves" to="/leaves/requests" class="btn-requests">Leave Requests (Approve)</router-link>
      </div>
    </div>

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
            <th>From</th>
            <th>To</th>
            <th>Type</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Approved/Rejected At</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="leave in (leaves || [])" :key="leave.id">
            <td>{{ leave.date_from }}</td>
            <td>{{ leave.date_to }}</td>
            <td>{{ leave.leave_type || '-' }}</td>
            <td>{{ leave.reason ? (leave.reason.slice(0, 40) + (leave.reason.length > 40 ? '...' : '')) : '-' }}</td>
            <td>
              <span :class="['status-badge', leave.status]">{{ leave.status }}</span>
            </td>
            <td>{{ leave.approved_at || '-' }}</td>
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
import { ref, computed, onMounted } from 'vue'
import { leaveService } from '../../services/leaveService'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const canManageLeaves = computed(() => authStore.hasAnyRole(['admin', 'hr']))

const leaves = ref([])
const loading = ref(false)
const error = ref('')
const filters = ref({ status: '', page: 1 })
const meta = ref(null)

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
    error.value = err.response?.data?.message || 'Failed to load leaves'
  } finally {
    loading.value = false
  }
}

const changePage = (p) => {
  filters.value.page = p
  loadLeaves()
}

onMounted(loadLeaves)
</script>

<style scoped>
.leave-list { padding: 20px; max-width: 1100px; margin: 0 auto; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.header h1 { margin: 0; color: #333; font-size: 1.75rem; }
.header-actions { display: flex; gap: 10px; }
.btn-primary { padding: 10px 20px; background: #265b99; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; }
.btn-requests { padding: 10px 16px; background: #198754; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; }
.filters { margin-bottom: 16px; }
.filter-select { padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
.table-container { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 14px; text-align: left; font-weight: 600; background: #f5f5f5; color: #333; }
.data-table td { padding: 14px; border-bottom: 1px solid #eee; }
.status-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; text-transform: capitalize; }
.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.approved { background: #d4edda; color: #155724; }
.status-badge.rejected { background: #f8d7da; color: #721c24; }
.pagination { display: flex; justify-content: center; align-items: center; gap: 12px; padding: 16px; }
.pagination button { padding: 8px 14px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer; }
.pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
.loading, .error { padding: 40px; text-align: center; color: #666; }
.error { color: #c00; }
</style>
