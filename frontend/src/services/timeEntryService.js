import api from './api'

export const timeEntryService = {
  // Get all time entries
  getAll(params = {}) {
    return api.get('/time-entries', { params })
  },

  // Get time entry by ID
  getById(id) {
    return api.get(`/time-entries/${id}`)
  },

  // Create time entry
  create(entryData) {
    return api.post('/time-entries', entryData)
  },

  // Update time entry
  update(id, entryData) {
    return api.put(`/time-entries/${id}`, entryData)
  },

  // Delete time entry
  delete(id) {
    return api.delete(`/time-entries/${id}`)
  },

  // Approve time entry
  approve(id) {
    return api.post(`/time-entries/${id}/approve`)
  },

  // Reject time entry
  reject(id, rejectionReason) {
    return api.post(`/time-entries/${id}/reject`, { rejection_reason: rejectionReason })
  }
}
