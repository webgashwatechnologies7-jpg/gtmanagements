import api from './api'

export const dailyPlanService = {
  // Assigned tasks for current user (for daily plan – all assigned tasks)
  getAssignedTasks() {
    return api.get('/daily-plans/assigned-tasks')
  },

  // Assigned projects for daily plan dropdown
  getAssignedProjects() {
    return api.get('/daily-plans/assigned-projects')
  },

  // Get all daily plans
  getAll(params = {}) {
    return api.get('/daily-plans', { params })
  },

  // TL: Get team members' daily plans
  getMyMembers(params = {}) {
    return api.get('/daily-plans/my-members', { params })
  },

  // Get daily plan by ID
  getById(id) {
    return api.get(`/daily-plans/${id}`)
  },

  // Get current user's daily plan for a date (for EOD – today's plan tasks)
  getByDate(date) {
    return api.get('/daily-plans/by-date', { params: { date } })
  },

  // Create daily plan
  create(planData) {
    return api.post('/daily-plans', planData)
  },

  // Update daily plan
  update(id, planData) {
    return api.put(`/daily-plans/${id}`, planData)
  },

  // Delete daily plan
  delete(id) {
    return api.delete(`/daily-plans/${id}`)
  },

  // Submit daily plan
  submit(id) {
    return api.post(`/daily-plans/${id}/submit`)
  }
}
