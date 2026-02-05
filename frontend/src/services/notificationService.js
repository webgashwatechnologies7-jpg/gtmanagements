import api from './api'

export const notificationService = {
  // Get all notifications
  getAll(params = {}) {
    return api.get('/notifications', { params })
  },

  // Get unread count
  getUnreadCount() {
    return api.get('/notifications/unread-count')
  },

  // Mark notification as read
  markAsRead(id) {
    return api.post(`/notifications/${id}/read`)
  },

  // Mark all notifications as read
  markAllAsRead() {
    return api.post('/notifications/read-all')
  },

  // Delete notification
  delete(id) {
    return api.delete(`/notifications/${id}`)
  },

  // Delete all read notifications
  deleteAllRead() {
    return api.delete('/notifications/read/all')
  }
}
