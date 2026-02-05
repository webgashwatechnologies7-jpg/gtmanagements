import api from './api'

export const attendanceService = {
  // Get all attendance records
  getAll(params = {}) {
    return api.get('/attendance', { params })
  },

  // Get attendance by ID
  getById(id) {
    return api.get(`/attendance/${id}`)
  },

  // Create attendance (manual marking)
  create(attendanceData) {
    return api.post('/attendance', attendanceData)
  },

  // Update attendance
  update(id, attendanceData) {
    return api.put(`/attendance/${id}`, attendanceData)
  },

  // Delete attendance
  delete(id) {
    return api.delete(`/attendance/${id}`)
  },

  // Get attendance statistics
  getStatistics(params = {}) {
    return api.get('/attendance/statistics', { params })
  },

  // Today's attendance (for dashboard)
  getToday(params = {}) {
    return api.get('/attendance/today', { params })
  },

  // Self check-in
  checkIn() {
    return api.post('/attendance/check-in')
  },

  // Self check-out
  checkOut() {
    return api.post('/attendance/check-out')
  }
}
