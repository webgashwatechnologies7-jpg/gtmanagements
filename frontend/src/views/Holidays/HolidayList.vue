<template>
  <div class="holiday-list">
    <div class="header">
      <h1>Holiday Management</h1>
      <router-link to="/holidays/create" class="btn-primary">Add Holiday</router-link>
    </div>

    <!-- Filters -->
    <div class="filters">
      <input
        type="text"
        v-model="filters.search"
        placeholder="Search holidays..."
        @input="debouncedSearch"
        class="search-input"
      />
      <select v-model="filters.type" @change="loadHolidays" class="filter-select">
        <option value="">All Types</option>
        <option value="holiday">Holiday</option>
        <option value="weekend">Weekend</option>
        <option value="custom">Custom</option>
      </select>
      <select v-model="filters.is_active" @change="loadHolidays" class="filter-select">
        <option value="">All Status</option>
        <option value="1">Active</option>
        <option value="0">Inactive</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading holidays...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Holidays Table -->
    <div v-if="!loading && !error" class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Name</th>
            <th>Type</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="holiday in (holidays || [])" :key="holiday.id">
            <td>{{ holiday.date }}</td>
            <td>{{ holiday.name }}</td>
            <td>
              <span class="type-badge">{{ holiday.type }}</span>
            </td>
            <td>
              <span :class="['status-badge', holiday.is_active ? 'active' : 'inactive']">
                {{ holiday.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="actions">
              <button @click="editHoliday(holiday.id)" class="btn-edit">Edit</button>
              <button @click="deleteHoliday(holiday.id)" class="btn-delete">Delete</button>
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
import { holidayService } from '../../services/holidayService'
import { debounce } from '../../utils/debounce'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'

const router = useRouter()
const toast = useToast()
const confirmStore = useConfirm()

const holidays = ref([])
const loading = ref(false)
const error = ref('')
const filters = ref({
  search: '',
  type: '',
  is_active: ''
})
const meta = ref(null)

const debouncedSearch = debounce(() => {
  loadHolidays()
}, 500)

const loadHolidays = async () => {
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

    const response = await holidayService.getAll(params)
    if (response.data.success) {
      holidays.value = response.data.data
      meta.value = response.data.meta
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load holidays'
  } finally {
    loading.value = false
  }
}

const changePage = (page) => {
  filters.value.page = page
  loadHolidays()
}

const editHoliday = (id) => {
  router.push(`/holidays/${id}/edit`)
}

const deleteHoliday = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to delete this holiday?')
  if (!ok) return
  try {
    await holidayService.delete(id)
    loadHolidays()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to delete holiday'))
  }
}

onMounted(() => {
  loadHolidays()
})
</script>

<style scoped>
.holiday-list {
  padding: 20px;
  max-width: 1200px;
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

.type-badge {
  padding: 4px 12px;
  background: #e2e3e5;
  color: #383d41;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
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
