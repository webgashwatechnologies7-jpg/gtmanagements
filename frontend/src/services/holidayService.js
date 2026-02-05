import api from './api'

export const holidayService = {
  // Get all holidays
  getAll(params = {}) {
    return api.get('/holidays', { params })
  },

  // Get holiday by ID
  getById(id) {
    return api.get(`/holidays/${id}`)
  },

  // Create holiday
  create(holidayData) {
    return api.post('/holidays', holidayData)
  },

  // Update holiday
  update(id, holidayData) {
    return api.put(`/holidays/${id}`, holidayData)
  },

  // Delete holiday
  delete(id) {
    return api.delete(`/holidays/${id}`)
  }
}
