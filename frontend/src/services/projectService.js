import api from './api'

export const projectService = {
  // Get all projects
  getAll(params = {}) {
    return api.get('/projects', { params })
  },

  // Get project by ID
  getById(id) {
    return api.get(`/projects/${id}`)
  },

  // Project history (create, assign, status change, delete - audit trail)
  getHistory(id, params = {}) {
    return api.get(`/projects/${id}/history`, { params })
  },

  // Create project
  create(projectData) {
    return api.post('/projects', projectData)
  },

  // Update project
  update(id, projectData) {
    return api.put(`/projects/${id}`, projectData)
  },

  // Delete project
  delete(id) {
    return api.delete(`/projects/${id}`)
  },

  // Assign to Project Manager
  assignToPM(id, projectManagerId) {
    return api.post(`/projects/${id}/assign-pm`, { project_manager_id: projectManagerId })
  },

  // Assign to Team Lead
  assignToTL(id, teamLeadId) {
    return api.post(`/projects/${id}/assign-tl`, { team_lead_id: teamLeadId })
  },

  // Assign to Employee
  assignToEmployee(id, employeeId) {
    return api.post(`/projects/${id}/assign-employee`, { employee_id: employeeId })
  }
}
