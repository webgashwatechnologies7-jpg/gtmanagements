<template>
  <div class="approval-list">
    <div class="header">
      <h1>Pending Approvals</h1>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading approvals...</div>

    <!-- Error -->
    <div v-if="error" class="error">{{ error }}</div>

    <!-- Approvals List -->
    <div v-if="!loading && !error" class="approvals-container">
      <div v-if="approvals.length === 0" class="empty-state">
        <p>No pending approvals</p>
      </div>

      <div v-for="approval in (approvals || [])" :key="approval.id" class="approval-card">
        <div class="approval-header">
          <h3>EOD Report - {{ approval.entity?.date || 'N/A' }}</h3>
          <span class="approval-type">{{ approval.entity_type }}</span>
        </div>

        <div class="approval-info">
          <p><strong>User:</strong> {{ approval.entity?.user?.name || 'N/A' }}</p>
          <p><strong>Date:</strong> {{ approval.entity?.date || 'N/A' }}</p>
          <p><strong>Items:</strong> {{ approval.entity?.items?.length || 0 }} item(s)</p>
        </div>

        <div class="approval-actions">
          <button @click="viewReport(approval.entity_id)" class="btn-view">View Report</button>
          <button @click="showApproveModal(approval)" class="btn-approve">Approve</button>
          <button @click="showRejectModal(approval)" class="btn-reject">Reject</button>
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

    <!-- Approve Modal -->
    <div v-if="showApprove" class="modal-overlay" @click="showApprove = false">
      <div class="modal-content" @click.stop>
        <h3>Approve Report</h3>
        <textarea v-model="approveComment" placeholder="Add a comment (optional)" rows="3"></textarea>
        <div class="modal-actions">
          <button @click="approveReport" class="btn-primary" :disabled="approving">
            {{ approving ? 'Approving...' : 'Approve' }}
          </button>
          <button @click="showApprove = false" class="btn-cancel">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="showReject" class="modal-overlay" @click="showReject = false">
      <div class="modal-content" @click.stop>
        <h3>Reject Report</h3>
        <textarea v-model="rejectComment" placeholder="Rejection reason (required)" rows="3" required></textarea>
        <div class="modal-actions">
          <button @click="rejectReport" class="btn-danger" :disabled="rejecting || !rejectComment">
            {{ rejecting ? 'Rejecting...' : 'Reject' }}
          </button>
          <button @click="showReject = false" class="btn-cancel">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { approvalService } from '../../services/approvalService'
import { eodReportService } from '../../services/eodReportService'
import { useToast } from '../../stores/toast'
import { getErrorMessage } from '../../utils/errorMessage'
import { logger } from '../../utils/logger'

const router = useRouter()
const toast = useToast()

const approvals = ref([])
const loading = ref(false)
const error = ref('')
const meta = ref(null)
const showApprove = ref(false)
const showReject = ref(false)
const currentApproval = ref(null)
const approveComment = ref('')
const rejectComment = ref('')
const approving = ref(false)
const rejecting = ref(false)

const loadApprovals = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await approvalService.getPending({ per_page: 15 })
    if (response.data.success) {
      approvals.value = response.data.data
      meta.value = response.data.meta

      // Load full report details for each approval
      for (let approval of approvals.value) {
        if (approval.entity_type === 'eod_report') {
          try {
            const reportResponse = await eodReportService.getById(approval.entity_id)
            if (reportResponse.data.success) {
              approval.entity = reportResponse.data.data
            }
          } catch (err) {
            logger.error('Failed to load report', err)
          }
        }
      }
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load approvals'
  } finally {
    loading.value = false
  }
}

const changePage = (page) => {
  loadApprovals()
}

const viewReport = (reportId) => {
  router.push(`/eod-reports/${reportId}`)
}

const showApproveModal = (approval) => {
  currentApproval.value = approval
  approveComment.value = ''
  showApprove.value = true
}

const showRejectModal = (approval) => {
  currentApproval.value = approval
  rejectComment.value = ''
  showReject.value = true
}

const approveReport = async () => {
  if (!currentApproval.value) return

  approving.value = true
  try {
    await approvalService.approveReport(currentApproval.value.entity_id, approveComment.value)
    showApprove.value = false
    loadApprovals()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to approve report'))
  } finally {
    approving.value = false
  }
}

const rejectReport = async () => {
  if (!currentApproval.value || !rejectComment.value) return

  rejecting.value = true
  try {
    await approvalService.rejectReport(currentApproval.value.entity_id, rejectComment.value)
    showReject.value = false
    loadApprovals()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Failed to reject report'))
  } finally {
    rejecting.value = false
  }
}

onMounted(() => {
  loadApprovals()
})
</script>

<style scoped>
.approval-list {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.header h1 {
  color: #333;
  font-size: 2rem;
  margin-bottom: 30px;
}

.approvals-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.approval-card {
  background: white;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.approval-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.approval-header h3 {
  margin: 0;
  color: #333;
}

.approval-type {
  padding: 4px 12px;
  background: #1a1a1a;
  color: white;
  border-radius: 12px;
  font-size: 12px;
}

.approval-info {
  margin-bottom: 15px;
}

.approval-info p {
  margin: 5px 0;
  color: #666;
}

.approval-actions {
  display: flex;
  gap: 10px;
}

.btn-view {
  padding: 8px 16px;
  background: #555;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-approve {
  padding: 8px 16px;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-reject {
  padding: 8px 16px;
  background: #333;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  padding: 30px;
  border-radius: 8px;
  max-width: 500px;
  width: 90%;
}

.modal-content h3 {
  margin-bottom: 20px;
  color: #333;
}

.modal-content textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  margin-bottom: 20px;
  font-family: inherit;
}

.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.btn-primary {
  padding: 10px 20px;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
}

.btn-danger {
  padding: 10px 20px;
  background: #333;
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
}

.btn-cancel {
  padding: 10px 20px;
  background: #6c757d;
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
}

.empty-state {
  text-align: center;
  padding: 60px;
  color: #999;
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

.loading, .error {
  text-align: center;
  padding: 40px;
  color: #666;
}

.error {
  color: #333;
}
</style>
