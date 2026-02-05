<template>
  <div class="daily-plan-list">
    <div class="header">
      <h1>My Members Daily Plans</h1>
      <router-link to="/daily-plans" class="btn-secondary">My Daily Plans</router-link>
    </div>

    <div class="filters">
      <input
        type="date"
        v-model="filters.date"
        @change="loadPlans"
        class="filter-input"
      />
      <select v-model="filters.status" @change="loadPlans" class="filter-select">
        <option value="">All Status</option>
        <option value="draft">Draft</option>
        <option value="submitted">Submitted</option>
      </select>
    </div>

    <div v-if="loading" class="loading">Loading daily plans...</div>
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
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="plan in plans" :key="plan.id">
            <td>{{ plan.user?.name || '-' }}</td>
            <td>{{ plan.date }}</td>
            <td>{{ plan.items?.length || 0 }} item(s)</td>
            <td>
              <span :class="['status-badge', plan.status]">{{ plan.status }}</span>
            </td>
            <td>{{ plan.submitted_at || '-' }}</td>
            <td class="actions">
              <router-link :to="{ path: `/daily-plans/${plan.id}`, query: { from: 'my-members' } }" class="btn-view">View</router-link>
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
import { dailyPlanService } from '../../services/dailyPlanService'

const plans = ref([])
const meta = ref(null)
const loading = ref(false)
const error = ref('')
const filters = ref({ date: '', status: '' })

const loadPlans = async () => {
  loading.value = true
  error.value = ''
  try {
    const params = { per_page: 15, ...filters.value }
    Object.keys(params).forEach(k => { if (params[k] === '') delete params[k] })
    const res = await dailyPlanService.getMyMembers(params)
    if (res.data.success) {
      plans.value = res.data.data || []
      meta.value = res.data.meta || null
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load daily plans'
  } finally {
    loading.value = false
  }
}

const changePage = (page) => {
  filters.value.page = page
  loadPlans()
}

onMounted(() => loadPlans())
</script>

<style scoped>
.daily-plan-list { padding: 20px; max-width: 1400px; margin: 0 auto; }
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
.status-badge.submitted { background: #d4edda; color: #155724; }
.actions { display: flex; gap: 8px; }
.btn-view { padding: 6px 12px; background: #0d6efd; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; }
.btn-view:hover { background: #0b5ed7; color: white; }
.pagination { display: flex; justify-content: center; align-items: center; gap: 15px; padding: 20px; }
.pagination button { padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer; }
.pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
.loading, .error { text-align: center; padding: 40px; color: #666; }
.error { color: #c00; }
</style>
