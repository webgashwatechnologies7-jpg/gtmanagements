import api from './api'

export const leaveService = {
  getAll(params = {}) {
    return api.get('/leaves', { params })
  },

  getById(id) {
    return api.get(`/leaves/${id}`)
  },

  apply(data) {
    return api.post('/leaves', data)
  },

  getDashboard() {
    return api.get('/leaves/dashboard')
  },

  approve(id) {
    return api.post(`/leaves/${id}/approve`)
  },

  reject(id, reason = '') {
    return api.post(`/leaves/${id}/reject`, { reason })
  }
}
