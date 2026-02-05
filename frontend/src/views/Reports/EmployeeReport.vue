<template>
  <div class="employee-report">
    <div class="header">
      <h1>{{ reportData?.employee?.name }} - {{ period }} Report</h1>
      <router-link to="/reports" class="btn-back">Back to Reports</router-link>
    </div>

    <!-- Filters -->
    <div class="filters">
      <input
        v-if="period === 'weekly'"
        type="date"
        v-model="filters.week_start"
        @change="loadReport"
        placeholder="Week Start"
        class="filter-input"
      />
      <input
        v-if="period === 'weekly'"
        type="date"
        v-model="filters.week_end"
        @change="loadReport"
        placeholder="Week End"
        class="filter-input"
      />
      <select
        v-if="period === 'monthly'"
        v-model="filters.month"
        @change="loadReport"
        class="filter-select"
      >
        <option v-for="m in 12" :key="m" :value="m">{{ getMonthName(m) }}</option>
      </select>
      <input
        v-if="period === 'monthly' || period === 'yearly'"
        type="number"
        v-model="filters.year"
        @change="loadReport"
        placeholder="Year"
        class="filter-input"
        min="2020"
        :max="new Date().getFullYear()"
      />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading report...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Report Data -->
    <div v-if="!loading && !error && reportData" class="report-content">
      <div class="report-section">
        <h2>Employee Information</h2>
        <div class="info-grid">
          <div class="info-item">
            <label>Name:</label>
            <span>{{ reportData.employee?.name }}</span>
          </div>
          <div class="info-item">
            <label>Email:</label>
            <span>{{ reportData.employee?.email }}</span>
          </div>
        </div>
      </div>

      <div class="report-section">
        <h2>Projects</h2>
        <div class="stats-grid">
          <div class="stat-card">
            <h3>Assigned</h3>
            <p class="stat-value">{{ reportData.projects?.assigned || 0 }}</p>
          </div>
          <div class="stat-card">
            <h3>Completed</h3>
            <p class="stat-value">{{ reportData.projects?.completed || 0 }}</p>
          </div>
        </div>
      </div>

      <div class="report-section">
        <h2>Attendance</h2>
        <div class="stats-grid">
          <div class="stat-card">
            <h3>Present</h3>
            <p class="stat-value">{{ reportData.attendance?.present || 0 }}</p>
          </div>
          <div class="stat-card">
            <h3>Absent</h3>
            <p class="stat-value">{{ reportData.attendance?.absent || 0 }}</p>
          </div>
        </div>
      </div>

      <div class="report-section">
        <h2>Time Tracking</h2>
        <div class="stats-grid">
          <div class="stat-card">
            <h3>Regular Hours</h3>
            <p class="stat-value">{{ reportData.time_tracking?.total_regular_hours || 0 }}h</p>
          </div>
          <div class="stat-card">
            <h3>Overtime Hours</h3>
            <p class="stat-value">{{ reportData.time_tracking?.total_overtime_hours || 0 }}h</p>
          </div>
        </div>
      </div>

      <div class="report-section">
        <h2>Reports</h2>
        <div class="stats-grid">
          <div class="stat-card">
            <h3>Submitted</h3>
            <p class="stat-value">{{ reportData.reports?.submitted || 0 }}</p>
          </div>
          <div class="stat-card">
            <h3>Approved</h3>
            <p class="stat-value">{{ reportData.reports?.approved || 0 }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { reportService } from '../../services/reportService'
import { exportService } from '../../services/exportService'
import { useToast } from '../../stores/toast'
import { getErrorMessage } from '../../utils/errorMessage'

const route = useRoute()
const toast = useToast()

const userId = computed(() => route.params.userId)
const period = computed(() => route.params.period)

const reportData = ref(null)
const loading = ref(false)
const error = ref('')
const filters = ref({
  week_start: '',
  week_end: '',
  month: new Date().getMonth() + 1,
  year: new Date().getFullYear()
})

const getMonthName = (month) => {
  const months = ['January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December']
  return months[month - 1]
}

const exportPDF = async () => {
  try {
    const params = {
      period: period.value,
      week_start: weekStart.value,
      week_end: weekEnd.value,
      month: month.value,
      year: year.value
    }
    const response = await exportService.exportEmployeeReportPDF(userId.value, params)
    exportService.downloadBlob(response.data, `employee-report-${userId.value}-${period.value}.pdf`)
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to export PDF'))
  }
}

const loadReport = async () => {
  loading.value = true
  error.value = ''

  try {
    let response
    const params = {}

    if (period.value === 'weekly') {
      if (filters.value.week_start) params.week_start = filters.value.week_start
      if (filters.value.week_end) params.week_end = filters.value.week_end
      response = await reportService.getWeeklyEmployeeReport(userId.value, params)
    } else if (period.value === 'monthly') {
      if (filters.value.month) params.month = filters.value.month
      if (filters.value.year) params.year = filters.value.year
      response = await reportService.getMonthlyEmployeeReport(userId.value, params)
    } else if (period.value === 'yearly') {
      if (filters.value.year) params.year = filters.value.year
      response = await reportService.getYearlyEmployeeReport(userId.value, params)
    }

    if (response?.data.success) {
      reportData.value = response.data.data
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load report'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  const q = route.query
  if (q.month) filters.value.month = parseInt(q.month, 10) || filters.value.month
  if (q.year) filters.value.year = parseInt(q.year, 10) || filters.value.year
  loadReport()
})
</script>

<style scoped>
.employee-report {
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
  flex-wrap: wrap;
}

.filter-input,
.filter-select {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
}

.report-content {
  display: flex;
  flex-direction: column;
  gap: 30px;
}

.report-section {
  background: white;
  padding: 25px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.report-section h2 {
  color: #333;
  font-size: 1.5rem;
  margin-bottom: 20px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.info-item label {
  font-weight: 500;
  color: #666;
}

.info-item span {
  font-size: 1.1rem;
  color: #333;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
}

.stat-card {
  background: #f8f9fa;
  padding: 20px;
  border-radius: 8px;
  text-align: center;
}

.stat-card h3 {
  margin: 0 0 10px 0;
  color: #666;
  font-size: 1rem;
}

.stat-value {
  margin: 0;
  font-size: 2rem;
  font-weight: bold;
  color: #1a1a1a;
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
