<template>
  <div class="member-detail">
    <div class="header">
      <div>
        <h1>Team Member Details</h1>
        <div class="sub" v-if="data?.team">Team: {{ data.team.name }}</div>
      </div>
      <router-link to="/team-members" class="btn-back">Back</router-link>
    </div>

    <div v-if="loading" class="loading">Loading member details...</div>
    <div v-else-if="error" class="error">{{ error }}</div>

    <template v-else-if="data">
      <div class="card">
        <h2>{{ data.employee?.name }}</h2>
        <div class="grid">
          <div class="item"><label>Email</label><div class="v">{{ data.employee?.email || '-' }}</div></div>
          <div class="item"><label>Employee ID</label><div class="v">{{ data.employee?.employee_id || '-' }}</div></div>
          <div class="item"><label>Phone</label><div class="v">{{ data.employee?.phone || '-' }}</div></div>
          <div class="item"><label>Status</label><div class="v">{{ data.employee?.status || '-' }}</div></div>
          <div class="item"><label>Created At</label><div class="v">{{ data.employee?.created_at || '-' }}</div></div>
        </div>
      </div>

      <div class="stats">
        <div class="stat">
          <div class="k">Total Assigned</div>
          <div class="n">{{ data.stats?.total_assigned ?? 0 }}</div>
        </div>
        <div class="stat">
          <div class="k">Assigned By Me</div>
          <div class="n">{{ data.stats?.assigned_by_me ?? 0 }}</div>
        </div>
        <div class="stat">
          <div class="k">Completed</div>
          <div class="n">{{ data.stats?.completed ?? 0 }}</div>
        </div>
      </div>

      <div class="card">
        <h3>Assigned Projects History</h3>
        <div class="table-wrap">
          <table class="data-table" v-if="assignments.length">
            <thead>
              <tr>
                <th>Project</th>
                <th>Status</th>
                <th>Assigned At</th>
                <th>Assigned By</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(a, idx) in assignments" :key="idx">
                <td>
                  <router-link :to="`/projects/${a.project.id}`" class="link">{{ a.project.name }}</router-link>
                </td>
                <td>{{ a.project.status }}</td>
                <td>{{ a.assigned_at || '-' }}</td>
                <td>
                  <span v-if="a.assigned_by_me" class="me">Me</span>
                  <span v-else>{{ a.assigned_by?.name || '-' }}</span>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="empty">No assigned projects found.</div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { teamMemberService } from '../../services/teamMemberService'

const route = useRoute()
const memberId = computed(() => route.params.id)

const data = ref(null)
const loading = ref(false)
const error = ref('')

const assignments = computed(() => {
  const list = data.value?.assignments
  return Array.isArray(list) ? list : []
})

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await teamMemberService.getMyMemberDetail(memberId.value)
    if (res.data.success) data.value = res.data.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load member details'
  } finally {
    loading.value = false
  }
}

onMounted(() => load())
</script>

<style scoped>
.member-detail {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
}
.header h1 {
  margin: 0;
  color: #333;
  font-size: 2rem;
}
.sub { color: #666; margin-top: 6px; }
.btn-back {
  padding: 10px 16px;
  background: #6c757d;
  color: white;
  text-decoration: none;
  border-radius: 6px;
  font-weight: 600;
}
.card {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  border: 1px solid #e6e6e6;
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
  margin-bottom: 16px;
}
.card h2 { margin: 0 0 10px; }
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}
.item label { font-size: 12px; color: #666; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.v { margin-top: 4px; color: #222; }
.stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.stat {
  background: #f7f9fb;
  border: 1px solid #e6e6e6;
  border-radius: 10px;
  padding: 14px 16px;
}
.stat .k { font-size: 12px; color: #666; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.stat .n { font-size: 28px; font-weight: 900; color: #265b99; margin-top: 6px; }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
.data-table thead { background: #f5f5f5; }
.link { color: #265b99; font-weight: 700; text-decoration: none; }
.link:hover { text-decoration: underline; }
.me {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 10px;
  background: #265b99;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
}
.empty, .loading, .error { padding: 18px; text-align: center; color: #666; }
.error { color: #333; }
@media (max-width: 768px) {
  .stats { grid-template-columns: 1fr; }
}
</style>

