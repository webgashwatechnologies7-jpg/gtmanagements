<template>
  <div class="leave-form">
    <div class="header">
      <h1>Apply for Leave</h1>
      <router-link to="/leaves" class="btn-back">Back to My Leave</router-link>
    </div>

    <form @submit.prevent="handleSubmit" v-if="!loading">
      <div class="form-group">
        <label>From Date *</label>
        <input type="date" v-model="form.date_from" required />
      </div>
      <div class="form-group">
        <label>To Date *</label>
        <input type="date" v-model="form.date_to" required />
      </div>
      <div class="form-group">
        <label>Leave Type</label>
        <select v-model="form.leave_type">
          <option value="">Select</option>
          <option value="sick">Sick</option>
          <option value="casual">Casual</option>
          <option value="earned">Earned</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Reason (optional)</label>
        <textarea v-model="form.reason" rows="3" placeholder="Short reason..."></textarea>
      </div>
      <div v-if="error" class="error-message">{{ error }}</div>
      <div class="form-actions">
        <button type="submit" class="btn-primary" :disabled="submitting">
          {{ submitting ? 'Submitting...' : 'Submit for Approval' }}
        </button>
        <router-link to="/leaves" class="btn-cancel">Cancel</router-link>
      </div>
    </form>
    <div v-if="loading" class="loading">Loading...</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { leaveService } from '../../services/leaveService'
import { useToast } from '../../stores/toast'

const router = useRouter()
const toast = useToast()
const form = ref({
  date_from: '',
  date_to: '',
  leave_type: '',
  reason: ''
})
const loading = ref(false)
const submitting = ref(false)
const error = ref('')

onMounted(() => {
  const t = new Date()
  form.value.date_from = t.toISOString().split('T')[0]
  form.value.date_to = t.toISOString().split('T')[0]
})

const handleSubmit = async () => {
  if (!form.value.date_from || !form.value.date_to) {
    error.value = 'From and To date required.'
    return
  }
  submitting.value = true
  error.value = ''
  try {
    const res = await leaveService.apply({
      date_from: form.value.date_from,
      date_to: form.value.date_to,
      leave_type: form.value.leave_type || null,
      reason: form.value.reason || null
    })
    if (res.data.success) {
      toast.showSuccess('Leave application submitted. Admin/HR will approve.')
      router.push('/leaves')
    }
  } catch (err) {
    error.value = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat().join(' ')
      : (err.response?.data?.message || 'Failed to submit leave')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.leave-form { padding: 20px; max-width: 560px; margin: 0 auto; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.header h1 { margin: 0; color: #333; font-size: 1.75rem; }
.btn-back { padding: 10px 18px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
.form-actions { display: flex; gap: 12px; margin-top: 24px; }
.btn-primary { padding: 12px 24px; background: #265b99; color: #fff; border: none; border-radius: 6px; font-weight: 500; cursor: pointer; }
.btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }
.btn-cancel { padding: 12px 24px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; }
.error-message { color: #c00; margin-bottom: 12px; font-size: 14px; }
.loading { padding: 40px; text-align: center; color: #666; }
</style>
