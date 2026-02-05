<template>
  <div id="app">
    <DashboardLayout v-if="showLayout">
      <router-view :key="route.fullPath" />
    </DashboardLayout>
    <router-view v-else :key="route.fullPath" />
    <Toast />
    <ConfirmModal />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from './stores/auth'
import DashboardLayout from './layouts/DashboardLayout.vue'
import Toast from './components/Toast.vue'
import ConfirmModal from './components/ConfirmModal.vue'

const route = useRoute()
const authStore = useAuthStore()

const showLayout = computed(() => route.meta.requiresAuth && authStore.isAuthenticated)
</script>

<style>
:root {
  --gt-primary: #404040;
  --gt-accent: #265b99;
  --gt-sidebar: #404040;
  --gt-text: #2c2c2c;
  --gt-text-muted: #5a5a5a;
  --gt-border: #d8dce0;
  --gt-bg: #ffffff;
  --gt-bg-alt: #eef1f5;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
  color: var(--gt-text);
  background: var(--gt-bg-alt);
}

#app {
  min-height: 100vh;
}

/* Global theme: #404040 + #265b99 across entire app */
#app .btn-primary,
#app a.btn-primary {
  background: #404040 !important;
  color: #fff !important;
  border-color: #404040 !important;
}
#app .btn-primary:hover,
#app a.btn-primary:hover {
  background: #265b99 !important;
  border-color: #265b99 !important;
}
#app .btn-secondary {
  background: #5a5a5a !important;
  color: #fff !important;
}
#app .btn-secondary:hover {
  background: #265b99 !important;
}
#app .btn-logout,
#app .btn-edit,
#app .btn-view,
#app .btn-report {
  background: #404040 !important;
  color: #fff !important;
}
#app .btn-edit:hover,
#app .btn-view:hover,
#app .btn-report:hover {
  background: #265b99 !important;
}
#app .btn-delete {
  background: #404040 !important;
  color: #fff !important;
}
#app .btn-delete:hover {
  background: #265b99 !important;
}
#app .data-table thead th,
#app .report-table thead th {
  background: #404040 !important;
  color: #fff !important;
  border-color: #404040 !important;
}
#app .header h1,
#app .dashboard-header h1,
#app .page-title {
  color: #404040 !important;
}
#app a.team-link,
#app a.project-link,
#app .team-link,
#app .project-link {
  color: #265b99 !important;
}
#app a.team-link:hover,
#app a.project-link:hover {
  color: #265b99 !important;
  text-decoration: underline;
}
#app .section-title {
  color: #404040 !important;
  border-left-color: #265b99 !important;
}
#app .btn-cancel,
#app .btn-back,
#app .btn-stats,
#app .btn-mark-all,
#app .btn-mark-read {
  background: #404040 !important;
  color: #fff !important;
}
#app .btn-cancel:hover,
#app .btn-back:hover,
#app .btn-stats:hover,
#app .btn-mark-all:hover,
#app .btn-mark-read:hover {
  background: #265b99 !important;
}
#app .btn-approve,
#app .btn-submit {
  background: #265b99 !important;
  color: #fff !important;
}
#app .btn-approve:hover,
#app .btn-submit:hover {
  background: #404040 !important;
}
#app input:focus,
#app select:focus,
#app textarea:focus {
  border-color: #265b99 !important;
  outline-color: #265b99;
}
</style>
