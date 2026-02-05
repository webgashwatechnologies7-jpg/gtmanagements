<template>
  <div class="team-list">
    <div class="header">
      <h1>Department Management</h1>
      <router-link to="/teams/create" class="btn-primary">Add New Department</router-link>
    </div>

    <!-- Filters -->
    <div class="filters">
      <input
        type="text"
        v-model="filters.search"
        placeholder="Search departments..."
        @input="debouncedSearch"
        class="search-input"
      />
      <select v-model="filters.status" @change="loadTeams" class="filter-select">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading teams...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Teams Table -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="team in (teams || [])" :key="team.id">
            <td>
              <router-link :to="`/teams/${team.id}`" class="team-link">
                {{ team.name }}
              </router-link>
            </td>
            <td>
              <span :class="['status-badge', team.status]">
                {{ team.status }}
              </span>
            </td>
            <td class="actions">
              <router-link :to="`/teams/${team.id}`" class="btn-view">View</router-link>
              <button @click="editTeam(team.id)" class="btn-edit">Edit</button>
              <button @click="deleteTeam(team.id)" class="btn-delete">Delete</button>
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
import { teamService } from '../../services/teamService'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'

const router = useRouter()
const toast = useToast()
const confirmStore = useConfirm()

const teams = ref([])
const loading = ref(false)
const error = ref('')
const filters = ref({
  search: '',
  status: ''
})
const meta = ref(null)

let searchTimeout = null

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadTeams()
  }, 500)
}

const loadTeams = async () => {
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

    const response = await teamService.getAll(params)
    if (response.data.success) {
      const data = response.data.data
      teams.value = Array.isArray(data) ? data : (data?.data ?? [])
      meta.value = response.data.meta ?? null
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load teams'
  } finally {
    loading.value = false
  }
}

const changePage = (page) => {
  filters.value.page = page
  loadTeams()
}

const editTeam = (id) => {
  router.push(`/teams/${id}/edit`)
}

const deleteTeam = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to delete this team?')
  if (!ok) return
  try {
    await teamService.delete(id)
    loadTeams()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to delete team'))
  }
}

onMounted(() => {
  loadTeams()
})
</script>

<style scoped>
.team-list {
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

.team-link {
  color: #1a1a1a;
  text-decoration: none;
  font-weight: 500;
}

.team-link:hover {
  text-decoration: underline;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.status-badge.active {
  background: #e0e0e0;
  color: #333;
}

.status-badge.inactive {
  background: #fff3cd;
  color: #856404;
}

.actions {
  display: flex;
  gap: 10px;
}

.btn-view {
  padding: 6px 12px;
  background: #555;
  color: white;
  text-decoration: none;
  border-radius: 4px;
  font-size: 12px;
}

.btn-view:hover {
  background: #138496;
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
</style>
