<template>
  <div class="eod-report-view">
    <div class="header">
      <h1>View EOD Report</h1>
      <div class="header-actions">
        <router-link :to="fromMyMembers ? '/eod-reports/my-members' : '/eod-reports'" class="btn-back">
          {{ fromMyMembers ? 'Back to My Members EODs' : 'Back to EOD Reports' }}
        </router-link>
        <template v-if="fromMyMembers && report && report.status === 'submitted'">
          <button @click="approveReport" class="btn-approve" :disabled="actionLoading">Approve</button>
          <button @click="rejectReport" class="btn-reject" :disabled="actionLoading">Reject</button>
        </template>
      </div>
    </div>
    <div v-if="loading" class="loading">Loading...</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <div v-else-if="report" class="detail-card">
      <div class="detail-row" v-if="report.user"><span class="label">Member</span><span class="value">{{ report.user.name }}</span></div>
      <div class="detail-row"><span class="label">Date</span><span class="value">{{ report.date }}</span></div>
      <div class="detail-row"><span class="label">Status</span><span :class="['status-badge', report.status]">{{ report.status }}</span></div>
      <div class="detail-row" v-if="report.submitted_at"><span class="label">Submitted At</span><span class="value">{{ report.submitted_at }}</span></div>
      <div class="section-title">Items (review each task and approve)</div>
      <div class="items-list">
        <div v-for="(item, idx) in report.items" :key="idx" class="item-row">
          <span class="item-project">{{ item.project?.name || 'Project' }}</span>
          <span class="item-summary">{{ item.work_summary }}</span>
          <span class="item-progress">{{ item.progress_percentage }}%</span>
          <span v-if="item.remaining_work" class="item-remaining">{{ item.remaining_work }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { eodReportService } from '../../services/eodReportService'
import { approvalService } from '../../services/approvalService'
import { useToast } from '../../stores/toast'
import { useConfirm } from '../../stores/confirm'
import { getErrorMessage } from '../../utils/errorMessage'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirmStore = useConfirm()
const report = ref(null)
const loading = ref(true)
const error = ref('')
const actionLoading = ref(false)

const fromMyMembers = computed(() => route.query.from === 'my-members')

const loadReport = async () => {
  try {
    const res = await eodReportService.getById(route.params.id)
    if (res.data.success) report.value = res.data.data
    else error.value = 'Report not found'
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load report'
  } finally {
    loading.value = false
  }
}

const approveReport = async () => {
  if (!report.value) return
  const ok = await confirmStore.confirm('Approve EOD', 'Is this EOD correct? Approve?')
  if (!ok) return
  actionLoading.value = true
  try {
    await approvalService.approveReport(report.value.id)
    if (fromMyMembers.value) router.push('/eod-reports/my-members')
    else loadReport()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Approve failed.'))
  } finally {
    actionLoading.value = false
  }
}

const rejectReport = async () => {
  const comment = prompt('Reject reason (optional):')
  if (comment === null || !report.value) return
  actionLoading.value = true
  try {
    await approvalService.rejectReport(report.value.id, comment || '')
    if (fromMyMembers.value) router.push('/eod-reports/my-members')
    else loadReport()
  } catch (err) {
    toast.showError(getErrorMessage(err, 'Reject nahi hua.'))
  } finally {
    actionLoading.value = false
  }
}

onMounted(loadReport)
</script>

<style scoped>
.eod-report-view { padding: 20px; max-width: 900px; margin: 0 auto; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.header h1 { color: #333; font-size: 2rem; margin: 0; }
.header-actions { display: flex; align-items: center; gap: 12px; }
.btn-back { padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; font-weight: 500; }
.btn-approve { padding: 10px 20px; background: #198754; color: white; border: none; border-radius: 5px; font-weight: 500; cursor: pointer; }
.btn-approve:hover:not(:disabled) { background: #157347; }
.btn-approve:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-reject { padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; font-weight: 500; cursor: pointer; }
.btn-reject:hover:not(:disabled) { background: #bb2d3b; }
.btn-reject:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-edit { padding: 10px 20px; background: #1a1a1a; color: white; text-decoration: none; border-radius: 5px; font-weight: 500; }
.loading, .error { padding: 40px; text-align: center; color: #666; }
.error { color: #c00; }
.detail-card { background: #f9f9f9; border-radius: 8px; padding: 24px; border: 1px solid #eee; }
.detail-row { display: flex; gap: 16px; margin-bottom: 12px; }
.detail-row .label { font-weight: 600; color: #555; min-width: 120px; }
.status-badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; text-transform: capitalize; }
.section-title { font-weight: 600; margin: 20px 0 12px; padding-top: 16px; border-top: 1px solid #eee; }
.items-list { display: flex; flex-direction: column; gap: 10px; }
.item-row { display: flex; flex-wrap: wrap; gap: 12px; padding: 12px; background: #fff; border-radius: 6px; border: 1px solid #eee; }
.item-project { font-weight: 600; color: #333; }
.item-summary { flex: 1; color: #333; }
.item-progress { color: #555; }
.item-remaining { width: 100%; font-size: 13px; color: #666; margin-top: 4px; }
</style>
