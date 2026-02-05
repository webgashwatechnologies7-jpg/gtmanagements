<template>
  <div class="attendance-statistics">
    <div class="header">
      <h1>Attendance Statistics</h1>
      <router-link to="/attendance" class="btn-back">Back to Attendance</router-link>
    </div>

    <!-- Filters -->
    <div class="filters">
      <input
        type="date"
        v-model="filters.date_from"
        @change="loadStatistics"
        placeholder="From Date"
        class="filter-input"
      />
      <input
        type="date"
        v-model="filters.date_to"
        @change="loadStatistics"
        placeholder="To Date"
        class="filter-input"
      />
      <button @click="loadStatistics" class="btn-filter">Apply</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading statistics...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Statistics Cards -->
    <div v-if="!loading && !error && statistics" class="stats-grid">
      <div class="stat-card">
        <h3>Total Days</h3>
        <p class="stat-value">{{ statistics.total_days }}</p>
      </div>
      <div class="stat-card present">
        <h3>Present</h3>
        <p class="stat-value">{{ statistics.present }}</p>
      </div>
      <div class="stat-card absent">
        <h3>Absent</h3>
        <p class="stat-value">{{ statistics.absent }}</p>
      </div>
      <div class="stat-card holiday">
        <h3>Holidays</h3>
        <p class="stat-value">{{ statistics.holiday }}</p>
      </div>
      <div class="stat-card leave">
        <h3>Leave</h3>
        <p class="stat-value">{{ statistics.leave }}</p>
      </div>
      <div class="stat-card half-day">
        <h3>Half Day</h3>
        <p class="stat-value">{{ statistics.half_day }}</p>
      </div>
      <div class="stat-card hours">
        <h3>Total Regular Hours</h3>
        <p class="stat-value">{{ statistics.total_regular_hours }}h</p>
      </div>
      <div class="stat-card overtime">
        <h3>Total Overtime Hours</h3>
        <p class="stat-value">{{ statistics.total_overtime_hours }}h</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { attendanceService } from '../../services/attendanceService'

const statistics = ref(null)
const loading = ref(false)
const error = ref('')
const filters = ref({
  date_from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
  date_to: new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0]
})

const loadStatistics = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      ...filters.value
    }

    const response = await attendanceService.getStatistics(params)
    if (response.data.success) {
      statistics.value = response.data.data
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load statistics'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadStatistics()
})
</script>

<style scoped>
.attendance-statistics {
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

.btn-back {
  padding: 10px 20px;
  background: #6c757d;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 500;
}

.filters {
  display: flex;
  gap: 15px;
  margin-bottom: 30px;
}

.filter-input {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
}

.btn-filter {
  padding: 10px 20px;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-weight: 500;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}

.stat-card {
  background: white;
  padding: 30px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  text-align: center;
}

.stat-card h3 {
  margin: 0 0 15px 0;
  color: #666;
  font-size: 1rem;
  font-weight: 500;
}

.stat-value {
  margin: 0;
  font-size: 2.5rem;
  font-weight: bold;
  color: #333;
}

.stat-card.present .stat-value {
  color: #1a1a1a;
}

.stat-card.absent .stat-value {
  color: #333;
}

.stat-card.holiday .stat-value {
  color: #555;
}

.stat-card.leave .stat-value {
  color: #666;
}

.stat-card.hours .stat-value {
  color: #1a1a1a;
}

.stat-card.overtime .stat-value {
  color: #555;
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
