<template>
  <div class="team-detail">
    <div class="header">
      <h1>Team Details</h1>
      <div class="header-actions">
        <router-link :to="`/teams/${teamId}/edit`" class="btn-edit">Edit Team</router-link>
        <router-link to="/teams" class="btn-back">Back to Teams</router-link>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading team details...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Team Info -->
    <div v-if="!loading && !error && team" class="team-info">
      <div class="info-card">
        <h2>{{ team.name }}</h2>
        <p v-if="team.description" class="description">{{ team.description }}</p>
        
        <div class="info-grid">
          <div class="info-item">
            <label>Status:</label>
            <span :class="['status-badge', team.status]">{{ team.status }}</span>
          </div>
          <div class="info-item" v-if="team.team_lead">
            <label>Team Lead:</label>
            <span>{{ team.team_lead.name }} ({{ team.team_lead.email }})</span>
          </div>
          <div class="info-item" v-if="team.project_manager">
            <label>Project Manager:</label>
            <span>{{ team.project_manager.name }} ({{ team.project_manager.email }})</span>
          </div>
          <div class="info-item">
            <label>Total Members:</label>
            <span>{{ team.members?.length || 0 }}</span>
          </div>
        </div>
      </div>

      <!-- Team Members -->
      <div class="members-section">
        <div class="section-header">
          <h3>Team Members ({{ team.members?.length || 0 }})</h3>
          <button @click="showAddMemberModal = true" class="btn-add-member">Add Members</button>
        </div>

        <div v-if="team.members && team.members.length > 0" class="members-list">
          <div v-for="member in team.members" :key="member.id" class="member-card">
            <div class="member-info">
              <h4>{{ member.name }}</h4>
              <p>{{ member.email }}</p>
              <p class="employee-id">ID: {{ member.employee_id }}</p>
              <span v-if="member.role_in_team" class="role-badge">{{ member.role_in_team }}</span>
            </div>
            <button @click="removeMember(member.id)" class="btn-remove">Remove</button>
          </div>
        </div>
        <div v-else class="empty-state">
          <p>No members in this team yet.</p>
        </div>
      </div>
    </div>

    <!-- Add Member Modal -->
    <div v-if="showAddMemberModal" class="modal-overlay" @click="showAddMemberModal = false">
      <div class="modal-content" @click.stop>
        <h3>Add Members to Team</h3>
        <div class="member-selection">
          <div v-for="user in availableUsers" :key="user.id" class="checkbox-item">
            <label>
              <input
                type="checkbox"
                :value="user.id"
                v-model="selectedMembers"
                :disabled="isMemberAlready(user.id)"
              />
              {{ user.name }} ({{ user.email }})
              <span v-if="isMemberAlready(user.id)" class="already-member">(Already a member)</span>
            </label>
          </div>
        </div>
        <div class="modal-actions">
          <button @click="addMembers" class="btn-primary" :disabled="selectedMembers.length === 0">
            Add Selected ({{ selectedMembers.length }})
          </button>
          <button @click="showAddMemberModal = false" class="btn-cancel">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { teamService } from '../../services/teamService'
import { userService } from '../../services/userService'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'
import { logger } from '../../utils/logger'

const route = useRoute()
const toast = useToast()
const confirmStore = useConfirm()
const teamId = computed(() => route.params.id)

const team = ref(null)
const availableUsers = ref([])
const loading = ref(false)
const error = ref('')
const showAddMemberModal = ref(false)
const selectedMembers = ref([])
const addingMembers = ref(false)

const isMemberAlready = (userId) => {
  if (!team.value || !team.value.members) return false
  return team.value.members.some(m => m.id === userId)
}

const loadTeam = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await teamService.getById(teamId.value)
    if (response.data.success) {
      team.value = response.data.data
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load team'
  } finally {
    loading.value = false
  }
}

const loadUsers = async () => {
  try {
    const response = await userService.getAll({ per_page: 100 })
    if (response.data.success) {
      availableUsers.value = response.data.data
    }
  } catch (err) {
    logger.error('Failed to load users:', err)
  }
}

const addMembers = async () => {
  if (selectedMembers.value.length === 0) return

  addingMembers.value = true
  try {
    await teamService.assignMembers(teamId.value, selectedMembers.value)
    selectedMembers.value = []
    showAddMemberModal.value = false
    loadTeam()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to add members'))
  } finally {
    addingMembers.value = false
  }
}

const removeMember = async (userId) => {
  const ok = await confirmStore.confirm('Confirm', 'Are you sure you want to remove this member from the team?')
  if (!ok) return
  try {
    await teamService.removeMember(teamId.value, userId)
    loadTeam()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to remove member'))
  }
}

onMounted(() => {
  loadTeam()
  loadUsers()
})
</script>

<style scoped>
.team-detail {
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

.header-actions {
  display: flex;
  gap: 10px;
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
  background: #218838;
}

.btn-back {
  padding: 10px 20px;
  background: #6c757d;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 500;
}

.btn-back:hover {
  background: #5a6268;
}

.info-card {
  background: white;
  padding: 30px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 30px;
}

.info-card h2 {
  color: #333;
  margin-bottom: 10px;
  font-size: 1.8rem;
}

.description {
  color: #666;
  margin-bottom: 20px;
  font-size: 1rem;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-top: 20px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.info-item label {
  font-weight: 600;
  color: #555;
  font-size: 0.9rem;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
  display: inline-block;
  width: fit-content;
}

.status-badge.active {
  background: #e0e0e0;
  color: #333;
}

.status-badge.inactive {
  background: #fff3cd;
  color: #856404;
}

.members-section {
  background: white;
  padding: 30px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.section-header h3 {
  color: #333;
  font-size: 1.5rem;
}

.btn-add-member {
  padding: 8px 16px;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-weight: 500;
}

.btn-add-member:hover {
  background: #333;
}

.members-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.member-card {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f9f9f9;
}

.member-info h4 {
  margin: 0 0 5px 0;
  color: #333;
}

.member-info p {
  margin: 5px 0;
  color: #666;
  font-size: 0.9rem;
}

.employee-id {
  font-size: 0.85rem;
  color: #999;
}

.role-badge {
  display: inline-block;
  padding: 2px 8px;
  background: #1a1a1a;
  color: white;
  border-radius: 10px;
  font-size: 11px;
  margin-top: 5px;
}

.btn-remove {
  padding: 6px 12px;
  background: #333;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.btn-remove:hover {
  background: #c82333;
}

.empty-state {
  text-align: center;
  padding: 40px;
  color: #999;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  padding: 30px;
  border-radius: 8px;
  max-width: 600px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
}

.modal-content h3 {
  margin-bottom: 20px;
  color: #333;
}

.member-selection {
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid #ddd;
  border-radius: 5px;
  padding: 15px;
  background: #f9f9f9;
  margin-bottom: 20px;
}

.checkbox-item {
  padding: 8px;
  background: white;
  margin-bottom: 8px;
  border-radius: 4px;
}

.checkbox-item label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  margin: 0;
}

.checkbox-item input[type="checkbox"]:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.already-member {
  color: #999;
  font-size: 0.85rem;
}

.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
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
