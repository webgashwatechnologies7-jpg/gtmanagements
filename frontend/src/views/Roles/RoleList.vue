<template>
  <div class="role-list">
    <div class="header">
      <h1>Role Management</h1>
      <router-link to="/roles/create" class="btn-primary">Add New Role</router-link>
    </div>

    <!-- Filters -->
    <div class="filters">
      <input
        type="text"
        v-model="filters.search"
        placeholder="Search roles..."
        @input="debouncedSearch"
        class="search-input"
      />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading roles...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Roles Table -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Description</th>
            <th>Users Count</th>
            <th>Permissions</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="role in (roles || [])" :key="role.id">
            <td>{{ role.id }}</td>
            <td>{{ role.name }}</td>
            <td><code>{{ role.slug }}</code></td>
            <td>{{ role.description || '-' }}</td>
            <td>{{ role.users_count || 0 }}</td>
            <td>
              <span v-if="role.permissions && role.permissions.length > 0">
                {{ role.permissions.length }} permission(s)
              </span>
              <span v-else>-</span>
            </td>
            <td class="actions">
              <button @click="editRole(role.id)" class="btn-edit">Edit</button>
              <button @click="deleteRole(role.id)" class="btn-delete" :disabled="isDefaultRole(role.slug)">
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { roleService } from '../../services/roleService'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'

const router = useRouter()
const toast = useToast()
const confirmStore = useConfirm()

const roles = ref([])
const loading = ref(false)
const error = ref('')
const filters = ref({
  search: ''
})

let searchTimeout = null

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadRoles()
  }, 500)
}

const loadRoles = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = { ...filters.value }
    if (params.search === '') delete params.search

    const response = await roleService.getAll(params)
    if (response.data.success) {
      const data = response.data.data
      roles.value = Array.isArray(data) ? data : (data?.data ?? [])
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load roles'
  } finally {
    loading.value = false
  }
}

const isDefaultRole = (slug) => {
  const defaultRoles = ['admin', 'project_manager', 'team_lead', 'employee']
  return defaultRoles.includes(slug)
}

const editRole = (id) => {
  router.push(`/roles/${id}/edit`)
}

const deleteRole = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to delete this role?')
  if (!ok) return
  try {
    await roleService.delete(id)
    loadRoles()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to delete role'))
  }
}

onMounted(() => {
  loadRoles()
})
</script>

<style scoped>
.role-list {
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
  margin-bottom: 20px;
}

.search-input {
  width: 100%;
  max-width: 400px;
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

.btn-delete:hover:not(:disabled) {
  background: #c82333;
}

.btn-delete:disabled {
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
