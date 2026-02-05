<template>
  <div class="report-list">
    <div class="header">
      <h1>Reports & Analytics</h1>
    </div>

    <!-- Admin: member's full month report by name -->
    <div v-if="isAdmin" class="report-section member-report-quick">
      <h2>Member Month Report</h2>
      <p class="section-hint">Enter any member's name and choose month – view their full month report: days present, projects assigned, work done, EOD.</p>
      <div class="member-quick-form">
        <input
          type="text"
          v-model="memberSearch"
          placeholder="Search member by name..."
          class="member-search-input"
        />
        <select v-model="selectedMemberId" class="member-select">
          <option value="">Select member</option>
          <option v-for="emp in filteredEmployees" :key="emp.id" :value="emp.id">{{ emp.name }} ({{ emp.email }})</option>
        </select>
        <select v-model="selectedMonth" class="month-select">
          <option v-for="m in 12" :key="m" :value="m">{{ getMonthName(m) }}</option>
        </select>
        <input type="number" v-model.number="selectedYear" min="2020" :max="new Date().getFullYear()" class="year-input" placeholder="Year" />
        <router-link
          :to="selectedMemberId ? { path: `/reports/employees/${selectedMemberId}/monthly`, query: { month: selectedMonth, year: selectedYear } } : '#'"
          class="btn-view-report"
          :class="{ disabled: !selectedMemberId }"
        >
          View Month Report
        </router-link>
      </div>
    </div>

    <div class="report-types">
      <div class="report-section">
        <h2>Team Reports</h2>
        <div class="report-cards">
          <div v-for="team in (teams || [])" :key="team.id" class="report-card">
            <h3>{{ team.name }}</h3>
            <div class="report-actions">
              <router-link :to="`/reports/teams/${team.id}/weekly`" class="btn-report">Weekly</router-link>
              <router-link :to="`/reports/teams/${team.id}/monthly`" class="btn-report">Monthly</router-link>
              <router-link :to="`/reports/teams/${team.id}/yearly`" class="btn-report">Yearly</router-link>
            </div>
          </div>
        </div>
      </div>

      <div class="report-section">
        <h2>Employee Reports</h2>
        <div class="report-cards">
          <div v-for="employee in (employees || [])" :key="employee.id" class="report-card">
            <h3>{{ employee.name }}</h3>
            <div class="report-actions">
              <router-link :to="`/reports/employees/${employee.id}/weekly`" class="btn-report">Weekly</router-link>
              <router-link :to="`/reports/employees/${employee.id}/monthly`" class="btn-report">Monthly</router-link>
              <router-link :to="`/reports/employees/${employee.id}/yearly`" class="btn-report">Yearly</router-link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { teamService } from '../../services/teamService'
import { userService } from '../../services/userService'
import { useAuthStore } from '../../stores/auth'
import { logger } from '../../utils/logger'

const authStore = useAuthStore()
const isAdmin = computed(() => authStore.hasRole('admin'))

const teams = ref([])
const employees = ref([])
const loading = ref(false)
const memberSearch = ref('')
const selectedMemberId = ref('')
const selectedMonth = ref(new Date().getMonth() + 1)
const selectedYear = ref(new Date().getFullYear())

const filteredEmployees = computed(() => {
  const list = employees.value || []
  const q = (memberSearch.value || '').trim().toLowerCase()
  if (!q) return list
  return list.filter(u => (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q))
})

const getMonthName = (m) => {
  const d = new Date(2000, m - 1, 1)
  return d.toLocaleString('default', { month: 'long' })
}

const loadData = async () => {
  loading.value = true

  try {
    // Load teams
    const teamsResponse = await teamService.getAll({ per_page: 100 })
    if (teamsResponse.data.success) {
      teams.value = teamsResponse.data.data
    }

    // Load employees based on role (admin: load all users for member report)
    if (authStore.hasRole('admin')) {
      const usersResponse = await userService.getAll({ per_page: 300 })
      if (usersResponse.data.success) {
        employees.value = usersResponse.data.data || []
      }
    } else if (authStore.hasRole('team_lead')) {
      // Load team members
      const teamsResponse = await teamService.getAll({ per_page: 100 })
      if (teamsResponse.data.success) {
        const userTeam = teamsResponse.data.data.find(t => 
          t.team_lead_id === authStore.user.id
        )
        if (userTeam) {
          employees.value = userTeam.members || []
        }
      }
    } else {
      // Employee can only see own reports
      employees.value = [authStore.user]
    }
  } catch (err) {
    logger.error('Failed to load data:', err)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.report-list {
  padding: 20px;
  max-width: 1400px;
  margin: 0 auto;
}

.header h1 {
  color: #333;
  font-size: 2rem;
  margin-bottom: 30px;
}

.report-types {
  display: flex;
  flex-direction: column;
  gap: 40px;
}

.report-section h2 {
  color: #333;
  font-size: 1.5rem;
  margin-bottom: 20px;
}

.report-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.report-card {
  background: white;
  padding: 25px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.report-card h3 {
  margin: 0 0 15px 0;
  color: #333;
  font-size: 1.2rem;
}

.report-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.btn-report {
  padding: 8px 16px;
  background: #1a1a1a;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-size: 14px;
  font-weight: 500;
  transition: background 0.2s;
}

.btn-report:hover {
  background: #333;
}

.member-report-quick {
  background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
  border: 1px solid #e0e0e0;
  border-radius: 10px;
  padding: 24px;
}

.section-hint {
  font-size: 0.95rem;
  color: #555;
  margin: 0 0 16px 0;
}

.member-quick-form {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}

.member-search-input {
  padding: 10px 14px;
  border: 1px solid #ddd;
  border-radius: 6px;
  min-width: 180px;
  font-size: 14px;
}

.member-select {
  padding: 10px 14px;
  border: 1px solid #ddd;
  border-radius: 6px;
  min-width: 220px;
  font-size: 14px;
}

.month-select, .year-input {
  padding: 10px 14px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
}

.year-input { width: 90px; }

.btn-view-report {
  padding: 10px 20px;
  background: #265b99;
  color: #fff;
  text-decoration: none;
  border-radius: 6px;
  font-weight: 500;
}

.btn-view-report:hover:not(.disabled) { background: #1e4a7a; }
.btn-view-report.disabled { opacity: 0.6; pointer-events: none; }
</style>
