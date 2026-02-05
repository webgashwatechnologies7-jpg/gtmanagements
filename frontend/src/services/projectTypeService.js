import api from './api'

export const projectTypeService = {
  // Get all project types
  getAll(params = {}) {
    return api.get('/project-types', { params })
  },

  // Get project type by ID
  getById(id) {
    return api.get(`/project-types/${id}`)
  },

  // Create project type
  create(projectTypeData) {
    return api.post('/project-types', projectTypeData)
  },

  // Update project type
  update(id, projectTypeData) {
    return api.put(`/project-types/${id}`, projectTypeData)
  },

  // Delete project type
  delete(id) {
    return api.delete(`/project-types/${id}`)
  }
}
