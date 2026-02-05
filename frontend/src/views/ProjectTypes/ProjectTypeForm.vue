<template>
  <div class="project-type-form">
    <div class="header">
      <h1>{{ isEdit ? 'Edit Project Type' : 'Create Project Type' }}</h1>
      <router-link to="/project-types" class="btn-back">Back to Project Types</router-link>
    </div>

    <form @submit.prevent="handleSubmit" v-if="!loading">
      <div class="form-grid">
        <div class="form-group">
          <label>Name *</label>
          <input type="text" v-model="form.name" required />
        </div>

        <div class="form-group">
          <label>Slug</label>
          <input
            type="text"
            v-model="form.slug"
            @input="slugTouched = true"
          />
          <small>Auto-generated from name if left empty</small>
        </div>

        <div class="form-group full-width">
          <label>Description</label>
          <textarea v-model="form.description" rows="3"></textarea>
        </div>

        <div class="form-group">
          <label>
            <input type="checkbox" v-model="form.is_active" />
            Active
          </label>
        </div>
      </div>

      <div v-if="error" class="error-message">{{ error }}</div>

      <div class="form-actions">
        <button type="submit" class="btn-primary" :disabled="submitting">
          {{ submitting ? 'Saving...' : (isEdit ? 'Update Project Type' : 'Create Project Type') }}
        </button>
        <router-link to="/project-types" class="btn-cancel">Cancel</router-link>
      </div>
    </form>

    <div v-if="loading" class="loading">Loading project type data...</div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { projectTypeService } from '../../services/projectTypeService'

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => !!route.params.id)

const form = ref({
  name: '',
  slug: '',
  description: '',
  is_active: true
})

const slugTouched = ref(false)

const slugify = (value) => {
  if (!value) return ''
  return String(value)
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '') // remove accents
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 100)
}

const loading = ref(false)
const submitting = ref(false)
const error = ref('')

watch(
  () => form.value.name,
  (newName, oldName) => {
    if (slugTouched.value) return

    const currentSlug = form.value.slug || ''
    const oldAuto = slugify(oldName || '')
    const shouldAutoUpdate = currentSlug === '' || currentSlug === oldAuto

    if (shouldAutoUpdate) {
      form.value.slug = slugify(newName || '')
    }
  }
)

const loadProjectType = async () => {
  if (!isEdit.value) return

  loading.value = true
  try {
    const response = await projectTypeService.getById(route.params.id)
    if (response.data.success) {
      const projectType = response.data.data
      form.value = {
        name: projectType.name,
        slug: projectType.slug,
        description: projectType.description || '',
        is_active: projectType.is_active
      }
      slugTouched.value = true // don't auto-overwrite existing slug during edit
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load project type'
  } finally {
    loading.value = false
  }
}

const handleSubmit = async () => {
  submitting.value = true
  error.value = ''

  try {
    const projectTypeData = { ...form.value }
    if (!projectTypeData.slug) {
      delete projectTypeData.slug // Let backend generate it
    }

    if (isEdit.value) {
      await projectTypeService.update(route.params.id, projectTypeData)
    } else {
      await projectTypeService.create(projectTypeData)
    }

    router.push('/project-types')
  } catch (err) {
    if (err.response?.data?.errors) {
      const errors = err.response.data.errors
      const firstError = Object.values(errors)[0]
      error.value = Array.isArray(firstError) ? firstError[0] : firstError
    } else {
      error.value = err.response?.data?.message || 'Failed to save project type'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadProjectType()
})
</script>

<style scoped>
.project-type-form {
  padding: 20px;
  max-width: 800px;
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

.form-group input[type="checkbox"] {
  width: auto;
  margin-right: 8px;
}

.form-group input,
.form-group textarea {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
  font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #1a1a1a;
}

.form-group small {
  margin-top: 4px;
  color: #666;
  font-size: 12px;
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
</style>
