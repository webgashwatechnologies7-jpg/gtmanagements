import api from './api'

export const taskService = {
  // Get all tasks
  getAll(params = {}) {
    return api.get('/tasks', { params })
  },

  // Get single task
  getById(id) {
    return api.get(`/tasks/${id}`)
  },

  // Create task
  create(data) {
    return api.post('/tasks', data)
  },

  // Update task
  update(id, data) {
    return api.put(`/tasks/${id}`, data)
  },

  // Delete task
  delete(id) {
    return api.delete(`/tasks/${id}`)
  },

  // Assign task to user
  assign(id, assignedToUserId) {
    return api.post(`/tasks/${id}/assign`, { assigned_to_user_id: assignedToUserId })
  },

  // Update task status (optional completion_notes when status = completed)
  updateStatus(id, status, completionNotes = null) {
    const payload = { status }
    if (completionNotes != null && completionNotes !== '') payload.completion_notes = completionNotes
    return api.patch(`/tasks/${id}/status`, payload)
  },

  // Get task statistics
  getStatistics(params = {}) {
    return api.get('/tasks/statistics', { params })
  }
}
