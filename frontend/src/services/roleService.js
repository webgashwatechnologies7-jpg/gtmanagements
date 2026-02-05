import api from './api'

export const roleService = {
  // Get all roles
  getAll(params = {}) {
    return api.get('/roles', { params })
  },

  // Get role by ID
  getById(id) {
    return api.get(`/roles/${id}`)
  },

  // Create role
  create(roleData) {
    return api.post('/roles', roleData)
  },

  // Update role
  update(id, roleData) {
    return api.put(`/roles/${id}`, roleData)
  },

  // Delete role
  delete(id) {
    return api.delete(`/roles/${id}`)
  },

  // Assign permissions to role
  assignPermissions(id, permissions) {
    return api.post(`/roles/${id}/assign-permissions`, { permissions })
  }
}
