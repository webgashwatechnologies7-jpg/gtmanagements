import { computed } from 'vue'
import { useAuthStore } from '../stores/auth'

/**
 * Composable for role checks. Use instead of repeating authStore.hasRole in every component.
 * @returns {{ isAdmin, isAdminOrHR, isPM, isTL, isEmployee }}
 */
export function useRoles() {
  const authStore = useAuthStore()
  return {
    isAdmin: computed(() => authStore.hasRole('admin')),
    isAdminOrHR: computed(() => authStore.hasAnyRole(['admin', 'hr'])),
    isPM: computed(() => authStore.hasRole('project_manager')),
    isTL: computed(() => authStore.hasAnyRole(['team_lead', 'team_leader'])),
    isEmployee: computed(() => authStore.hasRole('employee')),
  }
}
