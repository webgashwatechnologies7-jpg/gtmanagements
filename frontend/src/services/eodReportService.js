import api from './api'

export const eodReportService = {
  // Get all EOD reports
  getAll(params = {}) {
    return api.get('/eod-reports', { params })
  },

  // TL: get my members EOD reports
  getMyMembers(params = {}) {
    return api.get('/eod-reports/my-members', { params })
  },

  // Get EOD report by ID
  getById(id) {
    return api.get(`/eod-reports/${id}`)
  },

  // Create EOD report
  create(reportData) {
    return api.post('/eod-reports', reportData)
  },

  // Update EOD report
  update(id, reportData) {
    return api.put(`/eod-reports/${id}`, reportData)
  },

  // Delete EOD report
  delete(id) {
    return api.delete(`/eod-reports/${id}`)
  },

  // Submit EOD report
  submit(id) {
    return api.post(`/eod-reports/${id}/submit`)
  }
}
