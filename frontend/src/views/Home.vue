<template>
  <div class="home-page">
    <div class="page-header">
      <h1 class="page-title">My GTmanagement</h1>
      <p class="page-subtitle" v-if="authStore.user">
        {{ authStore.user.name }} · {{ authStore.user.email }}
      </p>
    </div>

    <section class="welcome-cards">
      <div class="welcome-card main">
        <h2>Welcome back</h2>
        <p v-if="authStore.user">You are logged in as <strong>{{ authStore.user.email }}</strong></p>
        <div class="role-tags" v-if="authStore.user?.roles?.length">
          <span v-for="role in authStore.user.roles" :key="role.id" class="tag">{{ role.name }}</span>
        </div>
      </div>
    </section>

    <section class="section">
      <h2 class="section-title">Quick access</h2>
      <div class="cards-grid">
        <router-link to="/dashboard" class="metric-card accent">
          <span class="card-label">Dashboard</span>
          <span class="card-value">Analytics</span>
          <p class="card-desc">View statistics</p>
        </router-link>
        <router-link to="/users" class="metric-card">
          <span class="card-label">Users</span>
          <span class="card-value">Manage</span>
          <p class="card-desc">Users & roles</p>
        </router-link>
        <router-link to="/teams" class="metric-card">
          <span class="card-label">Teams</span>
          <span class="card-value">Teams</span>
          <p class="card-desc">Teams & members</p>
        </router-link>
        <router-link to="/projects" class="metric-card">
          <span class="card-label">Projects</span>
          <span class="card-value">Projects</span>
          <p class="card-desc">Manage projects</p>
        </router-link>
        <router-link to="/tasks" class="metric-card">
          <span class="card-label">Tasks</span>
          <span class="card-value">Tasks</span>
          <p class="card-desc">Kanban & list</p>
        </router-link>
        <router-link to="/daily-plans" class="metric-card">
          <span class="card-label">Daily Plans</span>
          <span class="card-value">Plans</span>
          <p class="card-desc">Morning planning</p>
        </router-link>
        <router-link to="/eod-reports" class="metric-card">
          <span class="card-label">EOD Reports</span>
          <span class="card-value">Reports</span>
          <p class="card-desc">End of day</p>
        </router-link>
        <router-link to="/approvals" class="metric-card">
          <span class="card-label">Approvals</span>
          <span class="card-value">Pending</span>
          <p class="card-desc">Approve requests</p>
        </router-link>
        <router-link to="/attendance" class="metric-card">
          <span class="card-label">Attendance</span>
          <span class="card-value">Attendance</span>
          <p class="card-desc">Records</p>
        </router-link>
        <router-link to="/time-entries" class="metric-card">
          <span class="card-label">Time Entries</span>
          <span class="card-value">Time</span>
          <p class="card-desc">Track time</p>
        </router-link>
        <router-link to="/reports" class="metric-card">
          <span class="card-label">Reports</span>
          <span class="card-value">Reports</span>
          <p class="card-desc">Weekly / Monthly</p>
        </router-link>
        <router-link to="/notifications" class="metric-card">
          <span class="card-label">Notifications</span>
          <span class="card-value">Alerts</span>
          <p class="card-desc">View all</p>
        </router-link>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { logger } from '../utils/logger'

const authStore = useAuthStore()

onMounted(async () => {
  if (authStore.isAuthenticated && !authStore.user) {
    try {
      await authStore.fetchUser()
    } catch (error) {
      logger.error('Failed to fetch user:', error)
    }
  }
})
</script>

<style scoped>
.home-page {
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 24px;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #404040;
  margin: 0 0 4px 0;
}

.page-subtitle {
  font-size: 0.9375rem;
  color: #555;
  margin: 0;
}

.welcome-cards {
  margin-bottom: 28px;
}

.welcome-card {
  background: #fff;
  border-radius: 10px;
  padding: 24px 28px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  border: 1px solid #d8dce0;
}

.welcome-card.main h2 {
  font-size: 1.125rem;
  font-weight: 600;
  color: #404040;
  margin: 0 0 8px 0;
}

.welcome-card.main p {
  font-size: 0.9375rem;
  color: #555;
  margin: 0 0 12px 0;
}

.welcome-card.main p strong {
  color: #265b99;
}

.role-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag {
  background: rgba(38, 91, 153, 0.12);
  color: #265b99;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.8125rem;
  font-weight: 500;
}

.section {
  margin-bottom: 24px;
}

.section-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #404040;
  margin: 0 0 16px 0;
}

.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 16px;
}

.metric-card {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  border: 1px solid #d8dce0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  text-decoration: none;
  color: inherit;
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.metric-card:hover {
  border-color: #265b99;
  box-shadow: 0 6px 16px rgba(38, 91, 153, 0.15);
  transform: translateY(-2px);
}

.metric-card.accent {
  border-left: 4px solid #265b99;
}

.card-label {
  font-size: 0.75rem;
  font-weight: 500;
  color: #555;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.card-value {
  font-size: 1.125rem;
  font-weight: 700;
  color: #404040;
}

.metric-card:hover .card-value {
  color: #265b99;
}

.card-desc {
  font-size: 0.8125rem;
  color: #555;
  margin: 0;
}

@media (max-width: 768px) {
  .cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
