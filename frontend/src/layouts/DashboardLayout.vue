<template>
  <div class="dashboard-app">
    <div class="sidebar-overlay" :class="{ show: sidebarOpen && isMobile }" @click="sidebarOpen = false" aria-hidden="true"></div>
    <aside class="sidebar" :class="{ collapsed: !sidebarOpen }">
      <router-link to="/dashboard" class="sidebar-brand">
        <img src="/favicon.ico" alt="GT" class="sidebar-logo" />
        <span class="sidebar-title">GTmanagement</span>
      </router-link>
      <nav class="sidebar-nav">
        <!-- Admin: full sidebar with Users, Department, etc. (admin ko hamesha ye dikhe) -->
        <template v-if="isAdmin()">
          <router-link to="/dashboard" class="nav-item" active-class="active">
            <span class="nav-icon">📈</span>
            <span class="nav-text">Dashboard</span>
          </router-link>
          <router-link to="/users" class="nav-item" active-class="active">
            <span class="nav-icon">👥</span>
            <span class="nav-text">Users</span>
          </router-link>
          <router-link to="/roles" class="nav-item" active-class="active">
            <span class="nav-icon">🔐</span>
            <span class="nav-text">Roles</span>
          </router-link>
          <router-link to="/teams" class="nav-item" active-class="active">
            <span class="nav-icon">👥</span>
            <span class="nav-text">Department</span>
          </router-link>
          <router-link to="/project-types" class="nav-item" active-class="active">
            <span class="nav-icon">📋</span>
            <span class="nav-text">Project Types</span>
          </router-link>
          <router-link to="/projects" class="nav-item" active-class="active">
            <span class="nav-icon">🚀</span>
            <span class="nav-text">Projects</span>
          </router-link>
          <router-link to="/tasks" class="nav-item" active-class="active">
            <span class="nav-icon">✅</span>
            <span class="nav-text">Tasks</span>
          </router-link>
          <router-link to="/daily-plans" class="nav-item" active-class="active">
            <span class="nav-icon">📅</span>
            <span class="nav-text">Daily Plans</span>
          </router-link>
          <router-link to="/eod-reports" class="nav-item" active-class="active">
            <span class="nav-icon">📊</span>
            <span class="nav-text">EOD Reports</span>
          </router-link>
          <router-link to="/approvals" class="nav-item" active-class="active">
            <span class="nav-icon">✓</span>
            <span class="nav-text">Approvals</span>
          </router-link>
          <router-link to="/attendance" class="nav-item" active-class="active">
            <span class="nav-icon">📅</span>
            <span class="nav-text">Attendance</span>
          </router-link>
          <router-link to="/leaves" class="nav-item" active-class="active">
            <span class="nav-icon">🏖</span>
            <span class="nav-text">Leave</span>
          </router-link>
          <router-link to="/leaves/requests" class="nav-item" active-class="active">
            <span class="nav-icon">✓</span>
            <span class="nav-text">Leave Requests</span>
          </router-link>
          <router-link to="/holidays" class="nav-item" active-class="active">
            <span class="nav-icon">🎉</span>
            <span class="nav-text">Holidays</span>
          </router-link>
          <router-link to="/reports" class="nav-item" active-class="active">
            <span class="nav-icon">📈</span>
            <span class="nav-text">Reports</span>
          </router-link>
        </template>

        <!-- Team Lead sidebar (restricted) - when not admin -->
        <template v-else-if="isTL()">
          <router-link to="/dashboard" class="nav-item" active-class="active">
            <span class="nav-icon">📈</span>
            <span class="nav-text">Dashboard</span>
          </router-link>
          <router-link to="/projects" class="nav-item" active-class="active">
            <span class="nav-icon">🚀</span>
            <span class="nav-text">Projects</span>
          </router-link>
          <router-link to="/team-members" class="nav-item" active-class="active">
            <span class="nav-icon">👥</span>
            <span class="nav-text">Team Members</span>
          </router-link>
          <router-link to="/tasks" class="nav-item" active-class="active">
            <span class="nav-icon">✅</span>
            <span class="nav-text">Tasks</span>
          </router-link>
          <router-link to="/daily-plans" class="nav-item" active-class="active">
            <span class="nav-icon">📅</span>
            <span class="nav-text">Daily Plans</span>
          </router-link>
          <router-link to="/daily-plans/my-members" class="nav-item" active-class="active">
            <span class="nav-icon">📄</span>
            <span class="nav-text">My Members Daily Plans</span>
          </router-link>
          <router-link to="/eod-reports" class="nav-item" active-class="active">
            <span class="nav-icon">📊</span>
            <span class="nav-text">EOD Reports</span>
          </router-link>
          <router-link to="/eod-reports/my-members" class="nav-item" active-class="active">
            <span class="nav-icon">📄</span>
            <span class="nav-text">My Members EODs</span>
          </router-link>
          <router-link to="/attendance" class="nav-item" active-class="active">
            <span class="nav-icon">📅</span>
            <span class="nav-text">Attendance</span>
          </router-link>
          <router-link to="/leaves" class="nav-item" active-class="active">
            <span class="nav-icon">🏖</span>
            <span class="nav-text">Leave</span>
          </router-link>
          <router-link to="/reports" class="nav-item" active-class="active">
            <span class="nav-icon">📈</span>
            <span class="nav-text">Reports</span>
          </router-link>
        </template>

        <!-- Non-TL sidebar -->
        <template v-else>
          <router-link to="/dashboard" class="nav-item" active-class="active">
            <span class="nav-icon">📈</span>
            <span class="nav-text">Dashboard</span>
          </router-link>
          <router-link v-if="isAdmin()" to="/users" class="nav-item" active-class="active">
            <span class="nav-icon">👥</span>
            <span class="nav-text">Users</span>
          </router-link>
          <router-link v-if="isAdmin()" to="/roles" class="nav-item" active-class="active">
            <span class="nav-icon">🔐</span>
            <span class="nav-text">Roles</span>
          </router-link>
          <router-link v-if="isAdmin() || isPM()" to="/teams" class="nav-item" active-class="active">
            <span class="nav-icon">👥</span>
            <span class="nav-text">Department</span>
          </router-link>
          <router-link v-if="isAdmin()" to="/project-types" class="nav-item" active-class="active">
            <span class="nav-icon">📋</span>
            <span class="nav-text">Project Types</span>
          </router-link>
          <router-link to="/projects" class="nav-item" active-class="active">
            <span class="nav-icon">🚀</span>
            <span class="nav-text">Projects</span>
          </router-link>
          <router-link to="/tasks" class="nav-item" active-class="active">
            <span class="nav-icon">✅</span>
            <span class="nav-text">Tasks</span>
          </router-link>
          <router-link to="/daily-plans" class="nav-item" active-class="active">
            <span class="nav-icon">📅</span>
            <span class="nav-text">Daily Plans</span>
          </router-link>
          <router-link to="/eod-reports" class="nav-item" active-class="active">
            <span class="nav-icon">📊</span>
            <span class="nav-text">EOD Reports</span>
          </router-link>
          <router-link v-if="!isEmployee()" to="/approvals" class="nav-item" active-class="active">
            <span class="nav-icon">✓</span>
            <span class="nav-text">Approvals</span>
          </router-link>
          <router-link to="/attendance" class="nav-item" active-class="active">
            <span class="nav-icon">📅</span>
            <span class="nav-text">Attendance</span>
          </router-link>
          <router-link to="/leaves" class="nav-item" active-class="active">
            <span class="nav-icon">🏖</span>
            <span class="nav-text">Leave</span>
          </router-link>
          <router-link v-if="isAdminOrHR()" to="/leaves/requests" class="nav-item" active-class="active">
            <span class="nav-icon">✓</span>
            <span class="nav-text">Leave Requests</span>
          </router-link>
          <router-link v-if="isAdmin()" to="/holidays" class="nav-item" active-class="active">
            <span class="nav-icon">🎉</span>
            <span class="nav-text">Holidays</span>
          </router-link>
          <router-link to="/reports" class="nav-item" active-class="active">
            <span class="nav-icon">📈</span>
            <span class="nav-text">Reports</span>
          </router-link>
        </template>
      </nav>
    </aside>
    <div class="main-wrap">
      <header class="topbar">
        <div class="topbar-left">
          <button type="button" class="btn-menu" @click="sidebarOpen = !sidebarOpen" :aria-label="sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar'">
            <span class="menu-icon" :class="{ open: sidebarOpen }"></span>
            <span class="menu-icon" :class="{ open: sidebarOpen }"></span>
            <span class="menu-icon" :class="{ open: sidebarOpen }"></span>
          </button>
        </div>
        <div class="topbar-right">
          <span class="topbar-user">{{ authStore.user?.name }}</span>
          <button @click="handleLogout" class="btn-logout">Logout</button>
        </div>
      </header>
      <main class="content">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const sidebarOpen = ref(true)
const isMobile = ref(false)

const isAdmin = () => authStore.hasRole('admin')
const isAdminOrHR = () => authStore.hasAnyRole(['admin', 'hr'])
const isPM = () => authStore.hasRole('project_manager')
const isTL = () => authStore.hasAnyRole(['team_lead', 'team_leader'])
const isEmployee = () => authStore.hasRole('employee')

function checkMobile() {
  isMobile.value = window.innerWidth < 1024
}

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
  // Ensure user (roles) are loaded after refresh
  if (authStore.token && !authStore.user) {
    authStore.fetchUser().catch(() => {})
  }
})
onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})

function handleLogout() {
  authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.dashboard-app {
  display: flex;
  min-height: 100vh;
  position: relative;
}

/* Sidebar open/close */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 98;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.25s ease;
}
.sidebar-overlay.show {
  opacity: 1;
  pointer-events: auto;
}

.sidebar {
  width: 260px;
  min-width: 260px;
  background: linear-gradient(180deg, #404040 0%, #2d2d2d 50%, #262626 100%);
  border-right: 4px solid #265b99;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  transition: width 0.25s ease, min-width 0.25s ease;
  z-index: 99;
  box-shadow: 4px 0 24px rgba(0,0,0,0.18), inset 0 1px 0 rgba(255,255,255,0.04);
}
.sidebar.collapsed {
  width: 72px;
  min-width: 72px;
}
.sidebar.collapsed .sidebar-title,
.sidebar.collapsed .nav-text {
  opacity: 0;
  visibility: hidden;
  width: 0;
  overflow: hidden;
  white-space: nowrap;
}
.sidebar.collapsed .sidebar-brand {
  padding: 20px 0;
  justify-content: center;
}
.sidebar.collapsed .nav-item {
  justify-content: center;
  padding: 12px 0;
  margin: 0;
}
.sidebar.collapsed .nav-item.active {
  padding-left: 0;
  border-left: none;
  border-right: 3px solid #265b99;
}

@media (min-width: 1024px) {
  .sidebar-overlay {
    display: none !important;
  }
}

@media (max-width: 1023px) {
  .sidebar-overlay {
    display: block;
  }
  .sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    height: 100vh;
  }
  .sidebar.collapsed {
    width: 72px;
    min-width: 72px;
  }
  .sidebar:not(.collapsed) {
    box-shadow: 4px 0 24px rgba(0,0,0,0.2);
  }
}

.sidebar-brand {
  padding: 20px 20px;
  border-bottom: 2px solid rgba(38, 91, 153, 0.5);
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  transition: padding 0.25s ease;
  background: rgba(38, 91, 153, 0.12);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.06);
}

.sidebar-logo {
  height: 38px;
  width: auto;
  display: block;
  flex-shrink: 0;
  filter: brightness(1.05);
}

.sidebar-title {
  font-size: 1.2rem;
  font-weight: 700;
  color: #fff;
  letter-spacing: -0.02em;
  white-space: nowrap;
  transition: opacity 0.2s ease, width 0.2s ease;
  text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.sidebar-nav {
  padding: 16px 0;
  overflow-y: auto;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 13px 20px;
  color: rgba(255, 255, 255, 0.92);
  text-decoration: none;
  font-size: 0.9375rem;
  font-weight: 600;
  transition: background 0.2s, color 0.2s;
  border-left: 3px solid transparent;
  margin: 0 8px;
  border-radius: 8px;
}

.nav-item:hover {
  background: rgba(38, 91, 153, 0.45);
  color: #fff;
  border-left-color: rgba(255,255,255,0.3);
}

.nav-item.active {
  background: #265b99;
  color: #fff;
  border-left: 3px solid #fff;
  padding-left: 17px;
  box-shadow: 0 2px 8px rgba(38, 91, 153, 0.4);
}

.nav-icon {
  font-size: 1.25rem;
  flex-shrink: 0;
  line-height: 1;
  filter: drop-shadow(0 0 1px rgba(0,0,0,0.3));
}

.nav-text {
  white-space: nowrap;
  transition: opacity 0.2s ease, width 0.2s ease;
}

/* Topbar + menu button */
.main-wrap {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  background: #eef1f5;
}

.topbar {
  height: 56px;
  background: #fff;
  border-bottom: 3px solid #265b99;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px 0 16px;
  flex-shrink: 0;
}

.topbar-left {
  display: flex;
  align-items: center;
}

.btn-menu {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 5px;
  width: 40px;
  height: 40px;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  border-radius: 8px;
  transition: background 0.2s;
}
.btn-menu:hover {
  background: rgba(64, 64, 64, 0.08);
}

.menu-icon {
  display: block;
  width: 20px;
  height: 2px;
  background: #404040;
  border-radius: 1px;
  transition: transform 0.2s;
}
.menu-icon:nth-child(1).open { transform: translateY(7px) rotate(45deg); }
.menu-icon:nth-child(2).open { opacity: 0; }
.menu-icon:nth-child(3).open { transform: translateY(-7px) rotate(-45deg); }

.topbar-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.topbar-user {
  color: #404040;
  font-size: 0.9375rem;
  font-weight: 500;
}

.btn-logout {
  padding: 8px 16px;
  background: #404040;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-logout:hover {
  background: #265b99;
}

.content {
  flex: 1;
  padding: 24px;
  overflow-y: auto;
}
</style>
