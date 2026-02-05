<template>
  <div class="project-form">
    <div class="header">
      <h1>{{ isEdit ? 'Edit Project' : 'Create Project' }}</h1>
      <router-link to="/projects" class="btn-back">Back to Projects</router-link>
    </div>

    <form @submit.prevent="handleSubmit" v-if="!loading">
      <div class="form-grid">
        <div class="form-group full-width">
          <label>Project Type *</label>
          <select v-model="form.project_type_id" required>
            <option value="">Select Project Type</option>
            <option v-for="type in projectTypes" :key="type.id" :value="type.id">
              {{ type.name }}
            </option>
          </select>
        </div>

        <div class="form-group full-width">
          <label>Project Name *</label>
          <input type="text" v-model="form.name" required />
        </div>

        <div class="form-group full-width">
          <label>Description</label>
          <textarea v-model="form.description" rows="3"></textarea>
        </div>

        <div v-if="dynamicFields.length" class="form-group full-width dynamic-fields">
          <h3 class="section-title">Project Details</h3>
          <div class="dynamic-grid">
            <div
              v-for="field in dynamicFields"
              :key="field.key"
              class="form-group"
              :class="{ 'full-width': field.fullWidth }"
            >
              <label>
                {{ field.label }}
                <span v-if="field.required" class="required">*</span>
              </label>

              <input
                v-if="field.type === 'text'"
                type="text"
                v-model="form.custom_fields[field.key]"
                :required="field.required"
                :placeholder="field.placeholder || ''"
              />

              <input
                v-else-if="field.type === 'date'"
                type="date"
                v-model="form.custom_fields[field.key]"
                :required="field.required"
              />

              <textarea
                v-else-if="field.type === 'textarea'"
                v-model="form.custom_fields[field.key]"
                :required="field.required"
                :rows="field.rows || 3"
              ></textarea>

              <select
                v-else-if="field.type === 'select'"
                v-model="form.custom_fields[field.key]"
                :required="field.required"
              >
                <option value="">Select</option>
                <option v-for="opt in field.options || []" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>

              <div v-else-if="field.type === 'checkbox-group'" class="checkbox-grid">
                <label v-for="opt in field.options || []" :key="opt.value" class="checkbox-item">
                  <input
                    type="checkbox"
                    :value="opt.value"
                    v-model="form.custom_fields[field.key]"
                  />
                  {{ opt.label }}
                </label>
              </div>

              <label v-else-if="field.type === 'boolean'" class="inline-check">
                <input type="checkbox" v-model="form.custom_fields[field.key]" />
                {{ field.booleanLabel || 'Yes' }}
              </label>

              <small v-if="field.help" class="help">{{ field.help }}</small>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Priority</label>
          <select v-model="form.priority">
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select v-model="form.status">
            <option value="planning">Planning</option>
            <option value="active">Active</option>
            <option value="on_hold">On Hold</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <div class="form-group">
          <label>Deadline</label>
          <input type="date" v-model="form.deadline" />
        </div>

        <div class="form-group">
          <label>Estimated Hours</label>
          <input type="number" v-model="form.estimated_hours" step="0.01" min="0" />
        </div>

        <div class="form-group">
          <label>Start Date</label>
          <input type="date" v-model="form.start_date" />
        </div>

        <div v-if="isEdit" class="form-group">
          <label>Project Manager</label>
          <select v-model="form.project_manager_id">
            <option value="">Select Project Manager</option>
            <option v-for="user in availableUsers" :key="user.id" :value="user.id">
              {{ user.name }} ({{ user.email }})
            </option>
          </select>
        </div>

        <div v-if="isEdit" class="form-group">
          <label>Team</label>
          <select v-model="form.team_id">
            <option value="">Select Team</option>
            <option v-for="team in availableTeams" :key="team.id" :value="team.id">
              {{ team.name }}
            </option>
          </select>
        </div>

        <div v-if="isEdit" class="form-group">
          <label>Actual Hours</label>
          <input type="number" v-model="form.actual_hours" step="0.01" min="0" />
        </div>

        <div v-if="isEdit" class="form-group">
          <label>Completion Date</label>
          <input type="date" v-model="form.completion_date" />
        </div>
      </div>

      <div v-if="error" class="error-message">{{ error }}</div>

      <div class="form-actions">
        <button type="submit" class="btn-primary" :disabled="submitting">
          {{ submitting ? 'Saving...' : (isEdit ? 'Update Project' : 'Create Project') }}
        </button>
        <router-link to="/projects" class="btn-cancel">Cancel</router-link>
      </div>
    </form>

    <div v-if="loading" class="loading">Loading project data...</div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { projectService } from '../../services/projectService'
import { projectTypeService } from '../../services/projectTypeService'
import { userService } from '../../services/userService'
import { teamService } from '../../services/teamService'
import { logger } from '../../utils/logger'

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => !!route.params.id)

const form = ref({
  name: '',
  description: '',
  custom_fields: {},
  project_type_id: null,
  priority: 'medium',
  status: 'planning',
  deadline: null,
  estimated_hours: null,
  actual_hours: 0,
  project_manager_id: null,
  team_id: null,
  start_date: null,
  completion_date: null
})

const projectTypes = ref([])
const availableUsers = ref([])
const availableTeams = ref([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')

const selectedProjectType = computed(() => {
  const id = form.value.project_type_id
  if (!id) return null
  return projectTypes.value.find(t => String(t.id) === String(id)) || null
})

const getWebDevelopmentFallbackSchema = () => ([
  { key: 'date', label: 'Date', type: 'date' },
  { key: 'assigned_to', label: 'Assigned To', type: 'text' },
  { key: 'due_date', label: 'Due Date', type: 'date' },

  { key: 'company_name', label: 'Company Name', type: 'text', fullWidth: true },
  { key: 'domain_name', label: 'Domain Name', type: 'text', fullWidth: true },

  { key: 'hosting_needed', label: 'Hosting Needed', type: 'boolean', booleanLabel: 'Yes' },
  { key: 'ssl', label: 'SSL', type: 'boolean', booleanLabel: 'Yes' },
  { key: 'business_mail', label: 'Business Mail', type: 'text' },

  {
    key: 'predefined_pages',
    label: 'Pre-defined Pages',
    type: 'checkbox-group',
    fullWidth: true,
    options: [
      { value: 'home', label: 'Home' },
      { value: 'about-us', label: 'About-us' },
      { value: 'contact-us', label: 'Contact-us' },
      { value: 'disclaimer', label: 'Disclaimer' },
      { value: 'privacy-policy', label: 'Privacy Policy' },
      { value: 'terms-conditions', label: 'Terms & Condition' },
      { value: 'refund-policy', label: 'Refund Policy' },
      { value: 'vision-mission', label: 'Vision & Mission' },
      { value: 'blog', label: 'Blog' },
      { value: 'our-team', label: 'Our Team' },
      { value: 'gallery', label: 'Gallery' },
      { value: 'career', label: 'Career with us' },
      { value: 'client-testimonial', label: 'Client Testimonial' },
      { value: 'news-events', label: 'News & Events' },
      { value: 'portfolio', label: 'Portfolio' },
      { value: 'bookings', label: 'Bookings' }
    ]
  },
  {
    key: 'website_structure',
    label: 'Website Structure',
    type: 'select',
    options: [
      { value: 'dynamic', label: 'Dynamic' },
      { value: 'static', label: 'Static' }
    ]
  },
  { key: 'popup_form', label: 'Popup Form', type: 'boolean', booleanLabel: 'Yes' },

  { key: 'project_details', label: 'Project Details', type: 'textarea', rows: 5, fullWidth: true },

  { key: 'whatsapp', label: 'WhatsApp', type: 'text' },
  { key: 'additional', label: 'Additional', type: 'text' },
  { key: 'mobile_no', label: 'Mobile No.', type: 'text' },

  { key: 'gmb_needed', label: 'GMB Needed', type: 'boolean', booleanLabel: 'Yes' },
  { key: 'live_chat', label: 'Live Chat', type: 'boolean', booleanLabel: 'Yes' },

  { key: 'payment_gateway', label: 'Payment Gateway', type: 'boolean', booleanLabel: 'Yes' },
  { key: 'pay_now', label: 'Pay Now', type: 'boolean', booleanLabel: 'Yes' },

  { key: 'address_bank_details', label: 'Address / Bank Details', type: 'textarea', rows: 4, fullWidth: true },

  { key: 'logo', label: 'Logo', type: 'boolean', booleanLabel: 'Yes' },
  {
    key: 'social_media',
    label: 'Social Media',
    type: 'checkbox-group',
    fullWidth: true,
    options: [
      { value: 'facebook', label: 'Facebook' },
      { value: 'instagram', label: 'Instagram' },
      { value: 'twitter', label: 'Twitter' },
      { value: 'youtube', label: 'Youtube' },
      { value: 'linkedin', label: 'Linkedin' },
      { value: 'other', label: 'Other' }
    ]
  },
  {
    key: 'form_entries',
    label: 'Form Entries',
    type: 'checkbox-group',
    fullWidth: true,
    options: [
      { value: 'name', label: 'Name' },
      { value: 'email', label: 'Email' },
      { value: 'phone', label: 'Phone No.' },
      { value: 'date', label: 'Date' },
      { value: 'city', label: 'City' },
      { value: 'service', label: 'Service' }
    ]
  }
])

const getSeoFallbackSchema = () => ([
  { key: 'mail_logins', label: 'Mail Logins', type: 'textarea', rows: 3, fullWidth: true },
  { key: 'hosting_logins', label: 'Hosting Logins', type: 'textarea', rows: 3, fullWidth: true },
  { key: 'website_link', label: 'Website Link', type: 'text', fullWidth: true },
  { key: 'descriptions', label: 'Descriptions', type: 'textarea', rows: 4, fullWidth: true }
])

const getGoogleAdsFallbackSchema = () => ([
  { key: 'landing_page', label: 'Landing Page', type: 'text', fullWidth: true },
  { key: 'gmail_logins', label: 'Gmail Logins', type: 'textarea', rows: 3, fullWidth: true },
  { key: 'descriptions', label: 'Descriptions', type: 'textarea', rows: 4, fullWidth: true }
])

const dynamicFields = computed(() => {
  const type = selectedProjectType.value
  if (!type) return []

  const fields = Array.isArray(type.fields) ? type.fields : []
  if (fields.length) return fields

  // Fallback schemas (if fields not configured on project type)
  const slug = String(type.slug || '').toLowerCase()
  const name = String(type.name || '').toLowerCase()

  if (slug === 'web-development-project' || name === 'web developement project' || name === 'web development project') {
    return getWebDevelopmentFallbackSchema()
  }
  if (slug === 'seo-project' || name === 'seo project') return getSeoFallbackSchema()
  if (slug === 'google-ads-project' || name === 'google ads project') return getGoogleAdsFallbackSchema()

  return []
})

watch(
  () => form.value.project_type_id,
  () => {
    // Ensure custom_fields exists and initialize array fields
    if (!form.value.custom_fields || typeof form.value.custom_fields !== 'object') {
      form.value.custom_fields = {}
    }

    const schema = dynamicFields.value
    const allowedKeys = new Set(schema.map(f => f.key))

    // Remove keys not part of schema (keeps payload clean)
    Object.keys(form.value.custom_fields).forEach((k) => {
      if (!allowedKeys.has(k)) delete form.value.custom_fields[k]
    })

    // Initialize checkbox-group as arrays
    schema.forEach((f) => {
      if (f.type === 'checkbox-group' && !Array.isArray(form.value.custom_fields[f.key])) {
        form.value.custom_fields[f.key] = []
      }
      if (f.type === 'boolean' && typeof form.value.custom_fields[f.key] !== 'boolean') {
        // keep undefined unless user toggles; but make sure it's boolean once set
        if (form.value.custom_fields[f.key] === 0) form.value.custom_fields[f.key] = false
        if (form.value.custom_fields[f.key] === 1) form.value.custom_fields[f.key] = true
      }
    })
  },
  { immediate: true }
)

const loadProjectTypes = async () => {
  try {
    const response = await projectTypeService.getAll({ per_page: 100, is_active: 1 })
    if (response.data.success) {
      projectTypes.value = response.data.data
    }
  } catch (err) {
    logger.error('Failed to load project types:', err)
  }
}

const loadUsers = async () => {
  try {
    const response = await userService.getAll({ per_page: 100 })
    if (response.data.success) {
      availableUsers.value = response.data.data
    }
  } catch (err) {
    logger.error('Failed to load users:', err)
  }
}

const loadTeams = async () => {
  try {
    const response = await teamService.getAll({ per_page: 100, status: 'active' })
    if (response.data.success) {
      availableTeams.value = response.data.data
    }
  } catch (err) {
    logger.error('Failed to load teams:', err)
  }
}

const loadProject = async () => {
  if (!isEdit.value) return

  loading.value = true
  try {
    const response = await projectService.getById(route.params.id)
    if (response.data.success) {
      const project = response.data.data
      form.value = {
        name: project.name,
        description: project.description || '',
        custom_fields: project.custom_fields || {},
        project_type_id: project.project_type?.id || null,
        priority: project.priority,
        status: project.status,
        deadline: project.deadline || null,
        estimated_hours: project.estimated_hours || null,
        actual_hours: project.actual_hours || 0,
        project_manager_id: project.project_manager?.id || null,
        team_id: project.team?.id || null,
        start_date: project.start_date || null,
        completion_date: project.completion_date || null
      }
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load project'
  } finally {
    loading.value = false
  }
}

const handleSubmit = async () => {
  submitting.value = true
  error.value = ''

  try {
    const projectData = { ...form.value }
    
    // Convert null to empty string for optional fields
    Object.keys(projectData).forEach(key => {
      if (projectData[key] === null || projectData[key] === '') {
        delete projectData[key]
      }
    })
    // If custom_fields is empty object, omit it
    if (
      projectData.custom_fields &&
      typeof projectData.custom_fields === 'object' &&
      Object.keys(projectData.custom_fields).length === 0
    ) {
      delete projectData.custom_fields
    }

    if (isEdit.value) {
      await projectService.update(route.params.id, projectData)
    } else {
      await projectService.create(projectData)
    }

    router.push('/projects')
  } catch (err) {
    if (err.response?.data?.errors) {
      const errors = err.response.data.errors
      const firstError = Object.values(errors)[0]
      error.value = Array.isArray(firstError) ? firstError[0] : firstError
    } else {
      error.value = err.response?.data?.message || 'Failed to save project'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadProjectTypes()
  loadUsers()
  loadTeams()
  loadProject()
})
</script>

<style scoped>
.project-form {
  padding: 20px;
  max-width: 1200px;
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

.dynamic-fields {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #eee;
}

.section-title {
  margin: 0 0 12px;
  font-size: 1.1rem;
  color: #333;
}

.dynamic-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.checkbox-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px 14px;
  padding: 6px 0;
}

.checkbox-item {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-weight: 400;
  color: #555;
}

.inline-check {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-weight: 400;
  color: #555;
}

.required {
  color: #b00020;
  margin-left: 4px;
}

.help {
  margin-top: 4px;
  color: #666;
  font-size: 12px;
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

  .dynamic-grid {
    grid-template-columns: 1fr;
  }
}
</style>
