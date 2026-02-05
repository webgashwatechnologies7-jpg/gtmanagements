import api from './api'

export const bulkOperationService = {
  // Bulk update user status
  bulkUpdateUserStatus(userIds, status) {
    return api.post('/bulk/users/update-status', {
      user_ids: userIds,
      status
    })
  },

  // Bulk assign users to team
  bulkAssignToTeam(teamId, userIds) {
    return api.post(`/bulk/teams/${teamId}/assign-users`, {
      user_ids: userIds
    })
  },

  // Bulk assign projects to users
  bulkAssignProjects(projectIds, userIds) {
    return api.post('/bulk/projects/assign', {
      project_ids: projectIds,
      user_ids: userIds
    })
  },

  // Bulk delete users
  bulkDeleteUsers(userIds) {
    return api.post('/bulk/users/delete', {
      user_ids: userIds
    })
  }
}
