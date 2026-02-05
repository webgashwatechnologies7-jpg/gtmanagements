<template>
  <div class="project-type-list">
    <div class="header">
      <h1>Project Type Management</h1>
      <router-link to="/project-types/create" class="btn-primary">Add New Project Type</router-link>
    </div>

    <!-- Filters -->
    <div class="filters">
      <input
        type="text"
        v-model="filters.search"
        placeholder="Search project types..."
        @input="debouncedSearch"
        class="search-input"
      />
      <select v-model="filters.is_active" @change="loadProjectTypes" class="filter-select">
        <option value="">All Status</option>
        <option value="1">Active</option>
        <option value="0">Inactive</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading project types...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Project Types Table -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Description</th>
            <th>Projects</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="projectType in (projectTypes || [])" :key="projectType.id">
            <td>{{ projectType.id }}</td>
            <td>{{ projectType.name }}</td>
            <td><code>{{ projectType.slug }}</code></td>
            <td>{{ projectType.description || '-' }}</td>
            <td>{{ projectType.projects_count || 0 }}</td>
            <td>
              <span :class="['status-badge', projectType.is_active ? 'active' : 'inactive']">
                {{ projectType.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="actions">
              <button @click="editProjectType(projectType.id)" class="btn-edit">Edit</button>
              <button @click="deleteProjectType(projectType.id)" class="btn-delete" :disabled="projectType.projects_count > 0">
                Delete
              </button>
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
import { projectTypeService } from '../../services/projectTypeService'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'

const router = useRouter()
const toast = useToast()
const confirmStore = useConfirm()

const projectTypes = ref([])
const loading = ref(false)
const error = ref('')
const filters = ref({
  search: '',
  is_active: ''
})
const meta = ref(null)

let searchTimeout = null

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadProjectTypes()
  }, 500)
}

const loadProjectTypes = async () => {
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

    const response = await projectTypeService.getAll(params)
    if (response.data.success) {
      const data = response.data.data
      projectTypes.value = Array.isArray(data) ? data : (data?.data ?? [])
      meta.value = response.data.meta ?? null
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load project types'
  } finally {
    loading.value = false
  }
}

const changePage = (page) => {
  filters.value.page = page
  loadProjectTypes()
}

const editProjectType = (id) => {
  router.push(`/project-types/${id}/edit`)
}

const deleteProjectType = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to delete this project type?')
  if (!ok) return
  try {
    await projectTypeService.delete(id)
    loadProjectTypes()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to delete project type'))
  }
}

onMounted(() => {
  loadProjectTypes()
})
</script>

<style scoped>
.project-type-list {
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

.data-table code {
  background: #f5f5f5;
  padding: 2px 6px;
  border-radius: 3px;
  font-size: 12px;
  color: #1a1a1a;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
}

.status-badge.active {
  background: #e0e0e0;
  color: #333;
}

.status-badge.inactive {
  background: #e8e8e8;
  color: #333;
}

.actions {
  display: flex;
  gap: 10px;
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
  background: #333;
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

.btn-delete:hover:not(:disabled) {
  background: #1a1a1a;
}

.btn-delete:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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
