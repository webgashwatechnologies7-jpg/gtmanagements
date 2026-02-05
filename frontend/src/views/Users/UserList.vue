<template>
  <div class="user-list">
    <div class="header">
      <h1>User Management</h1>
      <router-link to="/users/create" class="btn-primary">Add New User</router-link>
    </div>

    <!-- Filters -->
    <div class="filters">
      <input
        type="text"
        v-model="filters.search"
        placeholder="Search by name, email, or employee ID..."
        @input="debouncedSearch"
        class="search-input"
      />
      <select v-model="filters.status" @change="loadUsers" class="filter-select">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="suspended">Suspended</option>
      </select>
      <select v-model="filters.role" @change="loadUsers" class="filter-select">
        <option value="">All Roles</option>
        <option v-for="role in (roles || [])" :key="role.id" :value="role.slug">
          {{ role.name }}
        </option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading users...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Users Table -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Employee ID</th>
            <th>Phone</th>
            <th>Status</th>
            <th>Roles</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in (users || [])" :key="user.id">
            <td>{{ user.id }}</td>
            <td>{{ user.name }}</td>
            <td>{{ user.email }}</td>
            <td>{{ user.employee_id }}</td>
            <td>{{ user.phone || '-' }}</td>
            <td>
              <span :class="['status-badge', user.status]">
                {{ user.status }}
              </span>
            </td>
            <td>
              <span v-for="(role, index) in (user.roles || [])" :key="role?.id ?? index" class="role-badge">
                {{ role.name }}<span v-if="index < (user.roles || []).length - 1">, </span>
              </span>
              <span v-if="!user.roles || (user.roles || []).length === 0">-</span>
            </td>
            <td class="actions">
              <button @click="editUser(user.id)" class="btn-edit">Edit</button>
              <button @click="deleteUser(user.id)" class="btn-delete">Delete</button>
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
import { userService } from '../../services/userService'
import { roleService } from '../../services/roleService'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'
import { logger } from '../../utils/logger'

const router = useRouter()
const toast = useToast()
const confirmStore = useConfirm()

const users = ref([])
const roles = ref([])
const loading = ref(false)
const error = ref('')
const filters = ref({
  search: '',
  status: '',
  role: ''
})
const meta = ref(null)

let searchTimeout = null

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadUsers()
  }, 500)
}

const loadUsers = async () => {
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

    const response = await userService.getAll(params)
    if (response.data.success) {
      users.value = Array.isArray(response.data.data) ? response.data.data : (response.data.data?.data ?? [])
      meta.value = response.data.meta ?? null
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load users'
  } finally {
    loading.value = false
  }
}

const loadRoles = async () => {
  try {
    const response = await roleService.getAll()
    if (response.data.success) {
      roles.value = response.data.data
    }
  } catch (err) {
    logger.error('Failed to load roles:', err)
  }
}

const changePage = (page) => {
  filters.value.page = page
  loadUsers()
}

const editUser = (id) => {
  router.push(`/users/${id}/edit`)
}

const deleteUser = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to delete this user?')
  if (!ok) return
  try {
    await userService.delete(id)
    loadUsers()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to delete user'))
  }
}

onMounted(() => {
  loadUsers()
  loadRoles()
})
</script>

<style scoped>
.user-list {
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
  background: #e8e8e8;
  color: #333;
}

.status-badge.suspended {
  background: #ddd;
  color: #333;
}

.role-badge {
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

.btn-delete:hover {
  background: #1a1a1a;
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
