<template>
  <div class="daily-plan-view">
    <div class="header">
      <h1>View Daily Plan</h1>
      <div class="header-actions">
        <router-link v-if="fromMyMembers" to="/daily-plans/my-members" class="btn-back">Back to My Members Daily Plans</router-link>
        <router-link v-else to="/daily-plans" class="btn-back">Back to Daily Plans</router-link>
        <router-link v-if="plan && plan.status === 'draft' && !fromMyMembers" :to="`/daily-plans/${plan.id}/edit`" class="btn-edit">Edit</router-link>
      </div>
    </div>

    <div v-if="loading" class="loading">Loading plan...</div>
    <div v-else-if="error" class="error">{{ error }}</div>

    <div v-else-if="plan" class="detail-card">
      <div class="detail-row">
        <span class="label">Date</span>
        <span class="value">{{ plan.date }}</span>
      </div>
      <div class="detail-row">
        <span class="label">Status</span>
        <span :class="['status-badge', plan.status]">{{ plan.status }}</span>
      </div>
      <div class="detail-row" v-if="plan.submitted_at">
        <span class="label">Submitted At</span>
        <span class="value">{{ plan.submitted_at }}</span>
      </div>
      <div class="detail-row" v-if="plan.user">
        <span class="label">User</span>
        <span class="value">{{ plan.user.name }} ({{ plan.user.email }})</span>
      </div>

      <div class="section-title">Plan Items</div>
      <div class="items-grouped">
        <template v-for="(group, projectName) in itemsByProject" :key="projectName">
          <div class="project-block">
            <div class="project-name">{{ projectName }}</div>
            <div class="task-list">
              <div v-for="(item, idx) in group" :key="idx" class="task-row">
                <div class="task-desc">{{ item.description || item.task?.name || 'Task' }}</div>
                <div class="task-meta">
                  <span class="task-hrs">{{ item.planned_hours }} hours</span>
                  <span class="task-priority">{{ item.priority }}</span>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <div class="total-row">
        <span class="label">Total Planned Hours</span>
        <span class="value">{{ totalHours.toFixed(1) }} hours</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { dailyPlanService } from '../../services/dailyPlanService'

const route = useRoute()
const plan = ref(null)
const loading = ref(true)
const error = ref('')

const fromMyMembers = computed(() => route.query.from === 'my-members')

const itemsByProject = computed(() => {
  const items = plan.value?.items || []
  const byProject = {}
  items.forEach(item => {
    const name = item.project?.name || 'Project #' + item.project_id
    if (!byProject[name]) byProject[name] = []
    byProject[name].push(item)
  })
  return byProject
})

const totalHours = computed(() => {
  const items = plan.value?.items || []
  return items.reduce((sum, i) => sum + (parseFloat(i.planned_hours) || 0), 0)
})

onMounted(async () => {
  try {
    const response = await dailyPlanService.getById(route.params.id)
    if (response.data.success) plan.value = response.data.data
    else error.value = 'Plan not found'
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load plan'
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.daily-plan-view {
  padding: 20px;
  max-width: 900px;
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

.header-actions {
  display: flex;
  gap: 12px;
}

.btn-back {
  padding: 10px 20px;
  background: #6c757d;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 500;
}

.btn-edit {
  padding: 10px 20px;
  background: #1a1a1a;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 500;
}

.btn-edit:hover {
  background: #333;
}

.loading, .error {
  text-align: center;
  padding: 40px;
  color: #666;
}

.error {
  color: #c00;
}

.detail-card {
  background: #f9f9f9;
  border-radius: 8px;
  padding: 24px;
  border: 1px solid #eee;
}

.detail-row {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
}

.detail-row .label {
  font-weight: 600;
  color: #555;
  min-width: 140px;
}

.detail-row .value {
  color: #333;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.status-badge.draft {
  background: #fff3cd;
  color: #333;
}

.status-badge.submitted {
  background: #d4edda;
  color: #155724;
}

.section-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #333;
  margin: 24px 0 12px 0;
  padding-top: 16px;
  border-top: 1px solid #eee;
}

.items-grouped {
  margin-bottom: 20px;
}

.project-block {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
  border: 1px solid #eee;
}

.project-name {
  font-weight: 600;
  color: #333;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid #eee;
}

.task-list {
  padding-left: 12px;
}

.task-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 10px 0;
  border-bottom: 1px solid #f0f0f0;
}

.task-row:last-child {
  border-bottom: none;
}

.task-desc {
  flex: 1;
  color: #333;
  font-size: 14px;
}

.task-meta {
  display: flex;
  gap: 12px;
  font-size: 13px;
  color: #666;
}

.task-hrs {
  font-weight: 500;
}

.task-priority {
  text-transform: capitalize;
}

.total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 0 0;
  margin-top: 16px;
  border-top: 2px solid #ddd;
  font-weight: 600;
}

.total-row .value {
  font-size: 1.1rem;
}
</style>
