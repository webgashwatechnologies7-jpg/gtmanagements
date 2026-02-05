import api from './api'

export const teamService = {
  // Get all teams
  getAll(params = {}) {
    return api.get('/teams', { params })
  },

  // Get team by ID
  getById(id) {
    return api.get(`/teams/${id}`)
  },

  // Create team
  create(teamData) {
    return api.post('/teams', teamData)
  },

  // Update team
  update(id, teamData) {
    return api.put(`/teams/${id}`, teamData)
  },

  // Delete team
  delete(id) {
    return api.delete(`/teams/${id}`)
  },

  // Get team statistics
  getStatistics(id) {
    return api.get(`/teams/${id}/statistics`)
  },

  // Assign members to team
  assignMembers(id, members) {
    return api.post(`/teams/${id}/assign-members`, { members })
  },

  // Remove member from team
  removeMember(teamId, userId) {
    return api.delete(`/teams/${teamId}/members/${userId}`)
  }
}
