import api from './api'

export const permissionService = {
  // Get all permissions
  getAll(params = {}) {
    return api.get('/permissions', { params })
  },

  // Get permission by ID
  getById(id) {
    return api.get(`/permissions/${id}`)
  }
}
