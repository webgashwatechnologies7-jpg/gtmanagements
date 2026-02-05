import api from './api'

export const exportService = {
  // Export team report as PDF
  exportTeamReportPDF(teamId, params = {}) {
    return api.get(`/export/teams/${teamId}/report/pdf`, {
      params,
      responseType: 'blob'
    })
  },

  // Export team report as CSV
  exportTeamReportCSV(teamId, params = {}) {
    return api.get(`/export/teams/${teamId}/report/csv`, {
      params,
      responseType: 'blob'
    })
  },

  // Export employee report as PDF
  exportEmployeeReportPDF(userId, params = {}) {
    return api.get(`/export/employees/${userId}/report/pdf`, {
      params,
      responseType: 'blob'
    })
  },

  // Export attendance as PDF
  exportAttendancePDF(params = {}) {
    return api.get('/export/attendance/pdf', {
      params,
      responseType: 'blob'
    })
  },

  // Helper to download blob
  downloadBlob(blob, filename) {
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  }
}
