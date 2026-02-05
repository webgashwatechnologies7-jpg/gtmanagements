<template>
  <div class="notification-list">
    <div class="header">
      <h1>Notifications</h1>
      <div class="header-actions">
        <button @click="markAllAsRead" class="btn-mark-all" :disabled="unreadCount === 0">
          Mark All as Read
        </button>
        <button @click="deleteAllRead" class="btn-delete-all" v-if="hasReadNotifications">
          Delete Read
        </button>
      </div>
    </div>

    <!-- Unread Count Badge -->
    <div v-if="unreadCount > 0" class="unread-badge">
      {{ unreadCount }} unread notification(s)
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading notifications...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Notifications List -->
    <div v-if="!loading && !error" class="notifications-container">
      <div v-if="notifications.length === 0" class="empty-state">
        <p>No notifications</p>
      </div>

      <div
        v-for="notification in (notifications || [])"
        :key="notification.id"
        :class="['notification-card', { unread: !notification.is_read }]"
        @click="handleNotificationClick(notification)"
      >
        <div class="notification-content">
          <div class="notification-header">
            <h3>{{ notification.title }}</h3>
            <span v-if="!notification.is_read" class="unread-dot"></span>
          </div>
          <p class="notification-message">{{ notification.message }}</p>
          <div class="notification-meta">
            <span class="notification-type">{{ notification.type }}</span>
            <span class="notification-time">{{ formatTime(notification.created_at) }}</span>
          </div>
        </div>
        <div class="notification-actions">
          <button
            v-if="!notification.is_read"
            @click.stop="markAsRead(notification.id)"
            class="btn-mark-read"
          >
            Mark Read
          </button>
          <button @click.stop="deleteNotification(notification.id)" class="btn-delete">
            Delete
          </button>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="meta && meta.last_page > 1" class="pagination">
        <button @click="changePage(meta.current_page - 1)" :disabled="meta.current_page === 1">
          Previous
        </button>
        <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button @click="changePage(meta.current_page + 1)" :disabled="meta.current_page === meta.last_page">
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { notificationService } from '../../services/notificationService'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'
import { logger } from '../../utils/logger'

const router = useRouter()
const toast = useToast()
const confirmStore = useConfirm()

const notifications = ref([])
const loading = ref(false)
const error = ref('')
const unreadCount = ref(0)
const meta = ref(null)

const hasReadNotifications = computed(() => {
  return notifications.value.some(n => n.is_read)
})

const loadNotifications = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await notificationService.getAll({ per_page: 20 })
    if (response.data.success) {
      notifications.value = response.data.data
      meta.value = response.data.meta
      unreadCount.value = response.data.meta?.unread_count || 0
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load notifications'
  } finally {
    loading.value = false
  }
}

const loadUnreadCount = async () => {
  try {
    const response = await notificationService.getUnreadCount()
    if (response.data.success) {
      unreadCount.value = response.data.data.count
    }
  } catch (err) {
    logger.error('Failed to load unread count', err)
  }
}

const markAsRead = async (id) => {
  try {
    await notificationService.markAsRead(id)
    loadNotifications()
    loadUnreadCount()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to mark as read'))
  }
}

const markAllAsRead = async () => {
  const ok = await confirmStore.confirm('Confirm', 'Mark all notifications as read?')
  if (!ok) return
  try {
    await notificationService.markAllAsRead()
    loadNotifications()
    loadUnreadCount()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to mark all as read'))
  }
}

const deleteNotification = async (id) => {
  const ok = await confirmStore.confirm('Confirm', 'Delete this notification?')
  if (!ok) return
  try {
    await notificationService.delete(id)
    loadNotifications()
    loadUnreadCount()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to delete notification'))
  }
}

const deleteAllRead = async () => {
  const ok = await confirmStore.confirm('Confirm', 'Delete all read notifications?')
  if (!ok) return
  try {
    await notificationService.deleteAllRead()
    loadNotifications()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to delete read notifications'))
  }
}

const handleNotificationClick = (notification) => {
  // Navigate based on entity type
  if (notification.entity_type && notification.entity_id) {
    if (notification.entity_type === 'eod_report') {
      router.push(`/eod-reports/${notification.entity_id}`)
    } else if (notification.entity_type === 'project') {
      router.push(`/projects/${notification.entity_id}`)
    }
  }

  // Mark as read if unread
  if (!notification.is_read) {
    markAsRead(notification.id)
  }
}

const formatTime = (dateString) => {
  const date = new Date(dateString)
  const now = new Date()
  const diff = now - date
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  if (days < 7) return `${days}d ago`
  return date.toLocaleDateString()
}

const changePage = (page) => {
  loadNotifications()
}

onMounted(() => {
  loadNotifications()
  loadUnreadCount()
  
  // Refresh unread count every 30 seconds
  setInterval(() => {
    loadUnreadCount()
  }, 30000)
})
</script>

<style scoped>
.notification-list {
  padding: 20px;
  max-width: 1000px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.header h1 {
  color: #333;
  font-size: 2rem;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.btn-mark-all,
.btn-delete-all {
  padding: 10px 20px;
  border: none;
  border-radius: 5px;
  font-weight: 500;
  cursor: pointer;
}

.btn-mark-all {
  background: #1a1a1a;
  color: white;
}

.btn-mark-all:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-delete-all {
  background: #333;
  color: white;
}

.unread-badge {
  background: #e8e8e8;
  color: #333;
  padding: 12px;
  border-radius: 5px;
  margin-bottom: 20px;
  text-align: center;
  font-weight: 500;
}

.notifications-container {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.notification-card {
  background: white;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  transition: all 0.2s;
}

.notification-card:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.notification-card.unread {
  border-left: 4px solid #1a1a1a;
  background: #f5f5f5;
}

.notification-content {
  flex: 1;
}

.notification-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
}

.notification-header h3 {
  margin: 0;
  color: #333;
  font-size: 1.1rem;
}

.unread-dot {
  width: 8px;
  height: 8px;
  background: #1a1a1a;
  border-radius: 50%;
}

.notification-message {
  margin: 0 0 10px 0;
  color: #666;
  line-height: 1.5;
}

.notification-meta {
  display: flex;
  gap: 15px;
  font-size: 0.9rem;
  color: #999;
}

.notification-type {
  text-transform: capitalize;
}

.notification-actions {
  display: flex;
  gap: 10px;
}

.btn-mark-read {
  padding: 6px 12px;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.btn-delete {
  padding: 6px 12px;
  background: #333;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  padding: 20px;
}

.pagination button {
  padding: 8px 16px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 4px;
  cursor: pointer;
}

.pagination button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.empty-state {
  text-align: center;
  padding: 60px;
  color: #999;
}

.loading, .error {
  text-align: center;
  padding: 40px;
  color: #666;
}

.error {
  color: #333;
}
</style>
