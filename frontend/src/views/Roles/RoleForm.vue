<template>
  <div class="role-form">
    <div class="header">
      <h1>{{ isEdit ? 'Edit Role' : 'Create Role' }}</h1>
      <router-link to="/roles" class="btn-back">Back to Roles</router-link>
    </div>

    <form @submit.prevent="handleSubmit" v-if="!loading">
      <div class="form-grid">
        <div class="form-group">
          <label>Role Name *</label>
          <input type="text" v-model="form.name" required />
        </div>

        <div class="form-group">
          <label>Slug *</label>
          <input type="text" v-model="form.slug" required />
          <small>Auto-generated from name (lowercase, underscores). You can edit if needed.</small>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select v-model="form.status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>

      <div v-if="error" class="error-message">{{ error }}</div>

      <div class="form-actions">
        <button type="submit" class="btn-primary" :disabled="submitting">
          {{ submitting ? 'Saving...' : (isEdit ? 'Update Role' : 'Create Role') }}
        </button>
        <router-link to="/roles" class="btn-cancel">Cancel</router-link>
      </div>
    </form>

    <div v-if="loading" class="loading">Loading role data...</div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { roleService } from '../../services/roleService'

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => !!route.params.id)

function slugify(text) {
  if (!text || typeof text !== 'string') return ''
  return text
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '_')
    .replace(/[^a-z0-9_]/g, '')
    .replace(/_+/g, '_')
    .replace(/^_|_$/g, '')
}

const form = ref({
  name: '',
  slug: '',
  status: 'active'
})

// Auto-generate slug from name (Create mode only)
watch(
  () => form.value.name,
  (newName) => {
    if (!isEdit.value) {
      form.value.slug = slugify(newName)
    }
  },
  { immediate: true }
)

const loading = ref(false)
const submitting = ref(false)
const error = ref('')

const loadRole = async () => {
  if (!isEdit.value) return

  loading.value = true
  try {
    const response = await roleService.getById(route.params.id)
    if (response.data.success) {
      const role = response.data.data
      form.value = {
        name: role.name,
        slug: role.slug,
        status: role.status || 'active'
      }
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load role'
  } finally {
    loading.value = false
  }
}

const handleSubmit = async () => {
  submitting.value = true
  error.value = ''

  try {
    const payload = { name: form.value.name, slug: form.value.slug, status: form.value.status }
    if (isEdit.value) {
      await roleService.update(route.params.id, payload)
    } else {
      await roleService.create(payload)
    }

    router.push('/roles')
  } catch (err) {
    if (err.response?.data?.errors) {
      const errors = err.response.data.errors
      const firstError = Object.values(errors)[0]
      error.value = Array.isArray(firstError) ? firstError[0] : firstError
    } else {
      error.value = err.response?.data?.message || 'Failed to save role'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadRole()
})
</script>

<style scoped>
.role-form {
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

.btn-back {
  padding: 10px 20px;
  background: #6c757d;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 500;
}

.btn-back:hover {
  background: #5a6268;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  margin-bottom: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  margin-bottom: 8px;
  font-weight: 500;
  color: #555;
}

.form-group input,
.form-group select,
.form-group textarea {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
  font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #1a1a1a;
}

.form-group small {
  margin-top: 4px;
  color: #666;
  font-size: 12px;
}

.permission-list {
  border: 1px solid #ddd;
  border-radius: 5px;
  padding: 15px;
  background: #f9f9f9;
  max-height: 400px;
  overflow-y: auto;
}

.permission-module {
  margin-bottom: 20px;
}

.permission-module:last-child {
  margin-bottom: 0;
}

.permission-module h3 {
  font-size: 16px;
  color: #1a1a1a;
  margin-bottom: 10px;
  padding-bottom: 8px;
  border-bottom: 1px solid #ddd;
}

.permission-items {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 10px;
}

.checkbox-item {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  padding: 8px;
  background: white;
  border-radius: 4px;
  transition: background 0.2s;
}

.checkbox-item:hover {
  background: #f0f0f0;
}

.checkbox-item input[type="checkbox"] {
  width: auto;
  cursor: pointer;
}

.checkbox-item small {
  color: #999;
  font-size: 11px;
}

.error-message {
  background: #eee;
  color: #333;
  padding: 12px;
  border-radius: 5px;
  margin-bottom: 20px;
}

.form-actions {
  display: flex;
  gap: 15px;
  margin-top: 30px;
}

.btn-primary {
  padding: 12px 24px;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 5px;
  font-size: 16px;
  font-weight: 500;
  cursor: pointer;
}

.btn-primary:hover:not(:disabled) {
  background: #333;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-cancel {
  padding: 12px 24px;
  background: #6c757d;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-size: 16px;
  font-weight: 500;
  display: inline-block;
}

.btn-cancel:hover {
  background: #5a6268;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #666;
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }

  .permission-items {
    grid-template-columns: 1fr;
  }
}
</style>
