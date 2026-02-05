<template>
  <div class="team-members">
    <div class="header">
      <h1>Team Members</h1>
      <div class="sub" v-if="teamName">Team: {{ teamName }}</div>
    </div>

    <div v-if="loading" class="loading">Loading team members...</div>
    <div v-else-if="error" class="error">{{ error }}</div>

    <div v-else class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Employee ID</th>
            <th>Phone</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in members" :key="m.id">
            <td>{{ m.id }}</td>
            <td>
              <router-link :to="`/team-members/${m.id}`" class="member-link">{{ m.name }}</router-link>
            </td>
            <td>{{ m.email }}</td>
            <td>{{ m.employee_id || '-' }}</td>
            <td>{{ m.phone || '-' }}</td>
            <td>{{ m.status || '-' }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="!members.length" class="empty">No team members found.</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { teamMemberService } from '../../services/teamMemberService'

const loading = ref(false)
const error = ref('')
const team = ref(null)
const members = ref([])

const teamName = computed(() => team.value?.name || '')

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await teamMemberService.getMyMembers()
    if (res.data.success) {
      team.value = res.data.data.team
      members.value = res.data.data.members || []
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load team members'
  } finally {
    loading.value = false
  }
}

onMounted(() => load())
</script>

<style scoped>
.team-members {
  padding: 20px;
  max-width: 1400px;
  margin: 0 auto;
}
.header {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 18px;
}
.header h1 {
  margin: 0;
  color: #333;
  font-size: 2rem;
}
.sub {
  color: #666;
  font-size: 0.95rem;
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
.data-table th,
.data-table td {
  padding: 15px;
  text-align: left;
  border-bottom: 1px solid #eee;
}
.data-table th {
  font-weight: 600;
  color: #333;
  border-bottom: 2px solid #ddd;
}
.member-link {
  color: #265b99;
  text-decoration: none;
  font-weight: 600;
}
.member-link:hover {
  text-decoration: underline;
}
.empty, .loading, .error {
  padding: 20px;
  text-align: center;
  color: #666;
}
.error {
  color: #333;
}
</style>

