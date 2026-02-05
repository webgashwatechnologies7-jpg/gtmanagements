<template>
  <div class="time-entry-list">
    <div class="header">
      <h1>Time Entries</h1>
      <router-link to="/time-entries/create" class="btn-primary">Add Time Entry</router-link>
    </div>

    <!-- Filters -->
    <div class="filters">
      <input
        type="date"
        v-model="filters.date"
        @change="loadEntries"
        class="filter-input"
      />
      <select v-model="filters.status" @change="loadEntries" class="filter-select">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
      <select v-model="filters.entry_type" @change="loadEntries" class="filter-select">
        <option value="">All Types</option>
        <option value="regular">Regular</option>
        <option value="overtime">Overtime</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading time entries...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Time Entries Table -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Project</th>
            <th>Hours</th>
            <th>Type</th>
            <th>Status</th>
            <th>Description</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="entry in (entries || [])" :key="entry.id">
            <td>{{ entry.date }}</td>
            <td>{{ entry.project?.name || '-' }}</td>
            <td>{{ entry.hours }}h</td>
            <td>
              <span :class="['type-badge', entry.entry_type]">
                {{ entry.entry_type }}
              </span>
            </td>
            <td>
              <span :class="['status-badge', entry.status]">
                {{ entry.status }}
              </span>
            </td>
            <td>{{ entry.description || '-' }}</td>
            <td class="actions">
              <button v-if="entry.status === 'pending'" @click="editEntry(entry.id)" class="btn-edit">Edit</button>
              <button v-if="entry.status === 'pending'" @click="approveEntry(entry.id)" class="btn-approve">Approve</button>
              <button v-if="entry.status === 'pending'" @click="rejectEntry(entry.id)" class="btn-reject">Reject</button>
              <button @click="deleteEntry(entry.id)" class="btn-delete">Delete</button>
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
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { timeEntryService } from '../../services/timeEntryService'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'

const router = useRouter()
const toast = useToast()
const confirmStore = useConfirm()

const entries = ref([])
const loading = ref(false)
const error = ref('')
const filters = ref({
  date: '',
  status: '',
  entry_type: ''
})
const meta = ref(null)

const loadEntries = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      per_page: 15,
      ...filters.value
    }

    Object.keys(params).forEach(key => {
      if (params[key] === '') delete params[key]
    })

    const response = await timeEntryService.getAll(params)
    if (response.data.success) {
      entries.value = response.data.data
      meta.value = response.data.meta
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load time entries'
  } finally {
    loading.value = false
  }
}

const changePage = (page) => {
  filters.value.page = page
  loadEntries()
}

const editEntry = (id) => {
  router.push(`/time-entries/${id}/edit`)
}

const approveEntry = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Approve this time entry?')
  if (!ok) return
  try {
    await timeEntryService.approve(id)
    loadEntries()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to approve entry'))
  }
}

const rejectEntry = async (id) => {
  const reason = prompt('Enter rejection reason:')
  if (!reason) return
  try {
    await timeEntryService.reject(id, reason)
    loadEntries()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to reject entry'))
  }
}

const deleteEntry = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to delete this time entry?')
  if (!ok) return
  try {
    await timeEntryService.delete(id)
    loadEntries()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to delete entry'))
  }
}

onMounted(() => {
  loadEntries()
})
</script>

<style scoped>
.time-entry-list {
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

.type-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.type-badge.regular {
  background: #e0e0e0;
  color: #333;
}

.type-badge.overtime {
  background: #e8e8e8;
  color: #333;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.status-badge.pending {
  background: #e8e8e8;
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
  flex-wrap: wrap;
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

.btn-approve {
  padding: 6px 12px;
  background: #555;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.btn-reject {
  padding: 6px 12px;
  background: #666;
  color: #333;
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
