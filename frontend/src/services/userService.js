import api from './api'

export const userService = {
  // Get all users
  getAll(params = {}) {
    return api.get('/users', { params })
  },

  // Get user by ID
  getById(id) {
    return api.get(`/users/${id}`)
  },

  // Create user
  create(userData) {
    return api.post('/users', userData)
  },

  // Update user
  update(id, userData) {
    return api.put(`/users/${id}`, userData)
  },

  // Delete user
  delete(id) {
    return api.delete(`/users/${id}`)
  },

  // Assign roles to user
  assignRoles(id, roles) {
    return api.post(`/users/${id}/assign-roles`, { roles })
  }
}
