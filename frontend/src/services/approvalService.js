import api from './api'

export const approvalService = {
  // Get pending approvals
  getPending(params = {}) {
    return api.get('/approvals/pending', { params })
  },

  // Approve EOD report
  approveReport(reportId, comment = '') {
    return api.post(`/eod-reports/${reportId}/approve`, { comment })
  },

  // Reject EOD report
  rejectReport(reportId, comment) {
    return api.post(`/eod-reports/${reportId}/reject`, { comment })
  }
}
