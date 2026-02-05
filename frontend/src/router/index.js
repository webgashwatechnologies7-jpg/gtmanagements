import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

// All route components use dynamic import() for code splitting & lazy loading (Phase 14 optimization)
const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: { name: 'dashboard' }
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/Login.vue'),
      meta: { requiresGuest: true }
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/Register.vue'),
      meta: { requiresGuest: true }
    },
    {
      path: '/users',
      name: 'users',
      component: () => import('../views/Users/UserList.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'hr'] }
    },
    {
      path: '/users/create',
      name: 'users.create',
      component: () => import('../views/Users/UserForm.vue'),
      meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
      path: '/users/:id/edit',
      name: 'users.edit',
      component: () => import('../views/Users/UserForm.vue'),
      meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
      path: '/roles',
      name: 'roles',
      component: () => import('../views/Roles/RoleList.vue'),
      meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
      path: '/roles/create',
      name: 'roles.create',
      component: () => import('../views/Roles/RoleForm.vue'),
      meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
      path: '/roles/:id/edit',
      name: 'roles.edit',
      component: () => import('../views/Roles/RoleForm.vue'),
      meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
      path: '/teams',
      name: 'teams',
      component: () => import('../views/Teams/TeamList.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'project_manager'] }
    },
    {
      path: '/teams/create',
      name: 'teams.create',
      component: () => import('../views/Teams/TeamForm.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'project_manager'] }
    },
    {
      path: '/teams/:id/edit',
      name: 'teams.edit',
      component: () => import('../views/Teams/TeamForm.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'project_manager'] }
    },
    {
      path: '/teams/:id',
      name: 'teams.show',
      component: () => import('../views/Teams/TeamDetail.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'project_manager', 'team_lead', 'team_leader'] }
    },
    {
      path: '/project-types',
      name: 'project-types',
      component: () => import('../views/ProjectTypes/ProjectTypeList.vue'),
      meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
      path: '/project-types/create',
      name: 'project-types.create',
      component: () => import('../views/ProjectTypes/ProjectTypeForm.vue'),
      meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
      path: '/project-types/:id/edit',
      name: 'project-types.edit',
      component: () => import('../views/ProjectTypes/ProjectTypeForm.vue'),
      meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
      path: '/projects',
      name: 'projects',
      component: () => import('../views/Projects/ProjectList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/projects/create',
      name: 'projects.create',
      component: () => import('../views/Projects/ProjectForm.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'project_manager'] }
    },
    {
      path: '/projects/:id/edit',
      name: 'projects.edit',
      component: () => import('../views/Projects/ProjectForm.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'project_manager', 'team_lead', 'team_leader'] }
    },
    {
      path: '/projects/:id',
      name: 'projects.show',
      component: () => import('../views/Projects/ProjectDetail.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/tasks',
      name: 'tasks',
      component: () => import('../views/Tasks/TaskList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/tasks/create',
      name: 'tasks.create',
      component: () => import('../views/Tasks/TaskForm.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/tasks/:id',
      name: 'tasks.show',
      component: () => import('../views/Tasks/TaskDetail.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/tasks/:id/edit',
      name: 'tasks.edit',
      component: () => import('../views/Tasks/TaskForm.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/tasks/board',
      name: 'tasks.board',
      component: () => import('../views/Tasks/TaskBoard.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/daily-plans',
      name: 'daily-plans',
      component: () => import('../views/DailyPlans/DailyPlanList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/daily-plans/create',
      name: 'daily-plans.create',
      component: () => import('../views/DailyPlans/DailyPlanForm.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/daily-plans/my-members',
      name: 'daily-plans.my-members',
      component: () => import('../views/DailyPlans/MyMembersDailyPlanList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/daily-plans/:id',
      name: 'daily-plans.view',
      component: () => import('../views/DailyPlans/DailyPlanView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/daily-plans/:id/edit',
      name: 'daily-plans.edit',
      component: () => import('../views/DailyPlans/DailyPlanForm.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/eod-reports',
      name: 'eod-reports',
      component: () => import('../views/EodReports/EodReportList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/eod-reports/my-members',
      name: 'eod-reports.my-members',
      component: () => import('../views/EodReports/MyMembersEodReportList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/eod-reports/create',
      name: 'eod-reports.create',
      component: () => import('../views/EodReports/EodReportForm.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/eod-reports/:id',
      name: 'eod-reports.view',
      component: () => import('../views/EodReports/EodReportView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/eod-reports/:id/edit',
      name: 'eod-reports.edit',
      component: () => import('../views/EodReports/EodReportForm.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/approvals',
      name: 'approvals',
      component: () => import('../views/Approvals/ApprovalList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/attendance',
      name: 'attendance',
      component: () => import('../views/Attendance/AttendanceList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/leaves',
      name: 'leaves',
      component: () => import('../views/Leaves/LeaveList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/leaves/apply',
      name: 'leaves.apply',
      component: () => import('../views/Leaves/LeaveForm.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/leaves/requests',
      name: 'leaves.requests',
      component: () => import('../views/Leaves/LeaveRequestList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/attendance/statistics',
      name: 'attendance.statistics',
      component: () => import('../views/Attendance/AttendanceStatistics.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/holidays',
      name: 'holidays',
      component: () => import('../views/Holidays/HolidayList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/holidays/create',
      name: 'holidays.create',
      component: () => import('../views/Holidays/HolidayForm.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/holidays/:id/edit',
      name: 'holidays.edit',
      component: () => import('../views/Holidays/HolidayForm.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/time-entries',
      name: 'time-entries',
      component: () => import('../views/TimeEntries/TimeEntryList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/time-entries/create',
      name: 'time-entries.create',
      component: () => import('../views/TimeEntries/TimeEntryForm.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/time-entries/:id/edit',
      name: 'time-entries.edit',
      component: () => import('../views/TimeEntries/TimeEntryForm.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../views/Dashboard/Dashboard.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/reports',
      name: 'reports',
      component: () => import('../views/Reports/ReportList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/reports/teams/:teamId/:period',
      name: 'reports.team',
      component: () => import('../views/Reports/TeamReport.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/reports/employees/:userId/:period',
      name: 'reports.employee',
      component: () => import('../views/Reports/EmployeeReport.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/notifications',
      name: 'notifications',
      component: () => import('../views/Notifications/NotificationList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/team-members',
      name: 'team-members',
      component: () => import('../views/TeamMembers/TeamMemberList.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/team-members/:id',
      name: 'team-members.show',
      component: () => import('../views/TeamMembers/TeamMemberDetail.vue'),
      meta: { requiresAuth: true }
    }
  ]
})

// Navigation guard
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'login' })
    return
  }
  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    next({ name: 'dashboard' })
    return
  }
  // Role check: ensure user is loaded when we have token (e.g. after refresh)
  if (to.meta.roles && authStore.isAuthenticated) {
    if (!authStore.user && authStore.token) {
      try {
        await authStore.fetchUser()
      } catch (e) {
        next({ name: 'login' })
        return
      }
    }
    const allowedRoles = to.meta.roles
    const hasRole = authStore.user?.roles?.some(r => allowedRoles.includes(r.slug))
    if (!hasRole) {
      next({ name: 'dashboard' })
    } else {
      next()
    }
    return
  }
  next()
})

export default router
