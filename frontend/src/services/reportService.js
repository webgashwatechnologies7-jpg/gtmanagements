import api from './api'

export const reportService = {
  // Team Reports
  getWeeklyTeamReport(teamId, params = {}) {
    return api.get(`/reports/teams/${teamId}/weekly`, { params })
  },

  getMonthlyTeamReport(teamId, params = {}) {
    return api.get(`/reports/teams/${teamId}/monthly`, { params })
  },

  getYearlyTeamReport(teamId, params = {}) {
    return api.get(`/reports/teams/${teamId}/yearly`, { params })
  },

  // Employee Reports
  getWeeklyEmployeeReport(userId, params = {}) {
    return api.get(`/reports/employees/${userId}/weekly`, { params })
  },

  getMonthlyEmployeeReport(userId, params = {}) {
    return api.get(`/reports/employees/${userId}/monthly`, { params })
  },

  getYearlyEmployeeReport(userId, params = {}) {
    return api.get(`/reports/employees/${userId}/yearly`, { params })
  }
}
