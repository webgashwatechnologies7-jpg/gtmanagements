import api from './api'

export const analyticsService = {
  // Get dashboard statistics (role-based)
  getDashboard() {
    return api.get('/analytics/dashboard')
  },

  // Get team analytics
  getTeamAnalytics(teamId, params = {}) {
    return api.get(`/analytics/teams/${teamId}`, { params })
  },

  // Get employee analytics
  getEmployeeAnalytics(userId, params = {}) {
    return api.get(`/analytics/employees/${userId}`, { params })
  }
}
